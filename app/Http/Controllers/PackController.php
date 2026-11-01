<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Pack;
use App\Models\User;
use App\Support\WhatsAppAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Page « Offres » : packs définis dans l'admin, commandés en paiement à la livraison. */
class PackController extends Controller
{
    public function index()
    {
        $packs = Pack::active()->with('items.product.variants')->orderBy('position')->orderByDesc('id')->get();

        return view('site.offers', ['packs' => $packs]);
    }

    public function show(Pack $pack)
    {
        abort_unless($pack->isAvailable(), 404);
        $pack->load(['items.product.variants.color', 'items.product.variants.size', 'items.product.category']);

        $user = Auth::user();

        return view('site.pack', [
            'pack' => $pack,
            'customer' => $user ? ['name' => $user->name, 'phone' => $user->phone, 'city' => $user->city, 'address' => $user->address] : [],
            'others' => Pack::active()->whereKeyNot($pack->id)->with('items.product')->orderBy('position')->take(3)->get(),
        ]);
    }

    public function order(Request $request)
    {
        $request->merge(['phone' => normalize_phone($request->input('phone'))]);

        $data = $request->validate([
            'pack_id' => ['required', 'integer', 'exists:packs,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5'],
            'variants' => ['nullable', 'array'],
            'variants.*' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'phone' => ['required', 'regex:/^0[5-7][0-9]{8}$/'],
            'city' => ['required', 'string', Rule::in(config('shop.cities'))],
            'address' => ['nullable', 'string', 'max:190'],
            'create_account' => ['nullable', 'boolean'],
            'email' => ['exclude_unless:create_account,1', 'nullable', 'required_if:create_account,1', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['exclude_unless:create_account,1', 'nullable', 'required_if:create_account,1', 'string', 'min:8'],
        ], [
            'phone.regex' => 'Entrez un numéro marocain valide (ex. 06 12 34 56 78).',
            'email.unique' => 'Un compte existe déjà avec cet e-mail : connectez-vous d’abord.',
            'email.required_if' => 'L’e-mail est obligatoire pour créer un compte.',
            'password.required_if' => 'Choisissez un mot de passe pour créer un compte.',
        ]);

        $pack = Pack::with(['items.product.variants.color', 'items.product.variants.size'])->findOrFail($data['pack_id']);
        if (! $pack->isAvailable()) {
            throw ValidationException::withMessages(['pack_id' => 'Cette offre n’est plus disponible.']);
        }
        $packQty = (int) $data['quantity'];

        // Variante choisie pour chaque produit du pack (couleur / taille)
        $lines = [];
        foreach ($pack->items as $item) {
            $product = $item->product;
            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages(['pack_id' => 'Un produit de cette offre n’est plus disponible.']);
            }
            $needed = $item->quantity * $packQty;
            $chosen = (int) ($data['variants'][$item->id] ?? 0);
            $variant = $chosen ? $product->variants->firstWhere('id', $chosen) : null;
            if (! $variant && $product->variants->count() === 1) {
                $variant = $product->variants->first();
            }
            if (! $variant) {
                throw ValidationException::withMessages(['variants.'.$item->id => 'Choisissez la couleur / taille pour « '.$product->name.' ».']);
            }
            if ($variant->stock < $needed) {
                throw ValidationException::withMessages(['variants.'.$item->id => $variant->stock > 0
                    ? "« {$product->name} » : il ne reste que {$variant->stock} pièce(s) dans ce choix."
                    : "« {$product->name} » : ce choix est épuisé, choisissez-en un autre."]);
            }
            $lines[] = compact('item', 'product', 'variant', 'needed');
        }

        // Le prix du pack est réparti sur les produits (au prorata de leur prix) : le total = prix du pack
        $total = $pack->price * $packQty;
        $base = array_sum(array_map(fn ($l) => $l['product']->price * $l['needed'], $lines)) ?: 1;
        $left = $total;
        foreach ($lines as $i => &$l) {
            $lineTotal = $i === array_key_last($lines) ? $left : (int) round($total * $l['product']->price * $l['needed'] / $base);
            $left -= $lineTotal;
            $l['total'] = $lineTotal;
            $l['unit'] = (int) round($lineTotal / max(1, $l['needed']));
        }
        unset($l);

        $order = DB::transaction(function () use ($data, $pack, $lines, $total) {
            $user = Auth::user();
            if (! $user && ! empty($data['create_account'])) {
                $user = User::create([
                    'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
                    'city' => $data['city'], 'address' => $data['address'] ?? null, 'password' => $data['password'],
                ]);
                Auth::login($user, true);
            }
            if ($user?->is_admin) {
                $user = null;
            }

            $customer = Customer::updateOrCreate(['phone' => $data['phone']], array_filter([
                'name' => $data['name'],
                'email' => $user?->email,
                'city' => $data['city'],
                'address' => $data['address'] ?? null,
                'user_id' => $user?->id,
            ]));

            $order = Order::create([
                'customer_id' => $customer->id,
                'user_id' => $user?->id,
                'customer_name' => $data['name'],
                'phone' => $data['phone'],
                'city' => $data['city'],
                'address' => $data['address'] ?? null,
                'status' => 'en_attente',
                'total' => $total,
            ]);

            foreach ($lines as $l) {
                $order->items()->create([
                    'product_id' => $l['product']->id,
                    'product_variant_id' => $l['variant']->id,
                    'pack_id' => $pack->id,
                    'pack_name' => $pack->name,
                    'product_name' => $l['product']->name,
                    'color_name' => $l['variant']->color?->name,
                    'size_name' => $l['variant']->size?->name,
                    'size_type' => $l['variant']->size?->type,
                    'is_custom' => false,
                    'quantity' => $l['needed'],
                    'unit_price' => $l['unit'],
                    'total' => $l['total'],
                ]);
            }

            return $order;
        });

        session()->push('my_orders', $order->id);

        try {
            WhatsAppAlert::newOrder($order);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('order.thanks', $order);
    }
}
