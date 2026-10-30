<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['phone' => ['nullable', 'string', 'max:64']]);
        $request->merge(['phone' => normalize_phone($request->input('phone'))]);

        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'variant_id' => ['nullable', 'integer'],
            'custom' => ['nullable', 'boolean'],
            'color_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'phone' => ['required', 'regex:/^0[5-7][0-9]{8}$/'],
            'city' => ['required', 'string', Rule::in(config('shop.cities'))],
            'address' => ['nullable', 'string', 'max:190'],
            'create_account' => ['nullable', 'boolean'],
            'email' => ['exclude_unless:create_account,1', 'nullable', 'required_if:create_account,1', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['exclude_unless:create_account,1', 'nullable', 'required_if:create_account,1', 'string', 'min:8', 'max:255'],
        ], [
            'phone.regex' => 'Entrez un numéro marocain valide (ex. 06 12 34 56 78).',
            'email.unique' => 'Un compte existe déjà avec cet e-mail : connectez-vous d’abord.',
            'email.required_if' => 'L’e-mail est obligatoire pour créer un compte.',
            'password.required_if' => 'Choisissez un mot de passe pour créer un compte.',
        ]);

        $product = Product::active()->with('variants.color', 'variants.size')->findOrFail($data['product_id']);
        $custom = (bool) ($data['custom'] ?? false) && $product->is_custom_possible;
        $variant = null;

        if (! $custom) {
            $variant = ! empty($data['variant_id']) ? $product->variants->firstWhere('id', (int) $data['variant_id']) : null;
            if (! $variant && $product->variants->count() === 1) {
                $variant = $product->variants->first();
            }
            if (! $variant) {
                throw ValidationException::withMessages(['variant_id' => 'Choisissez une couleur et une taille.']);
            }
            if ($variant->stock < $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => $variant->stock > 0
                    ? "Il ne reste que {$variant->stock} pièce(s) dans cette taille."
                    : 'Cette taille est épuisée. Choisissez-en une autre.']);
            }
        }

        [$order, $newUser] = DB::transaction(function () use ($data, $product, $variant, $custom) {
            $user = Auth::user();
            $newUser = null;
            if (! $user && ! empty($data['create_account'])) {
                $user = User::create([
                    'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
                    'city' => $data['city'], 'address' => $data['address'] ?? null, 'password' => $data['password'],
                ]);
                $newUser = $user;
            }
            if ($user?->is_admin) {
                $user = null; // une commande test passée depuis le compte admin reste « sans compte »
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
                'total' => $product->price * $data['quantity'],
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product->name,
                'color_name' => $variant?->color?->name
                    ?? ($custom && ! empty($data['color_id']) ? $product->availableColors()->firstWhere('id', (int) $data['color_id'])?->name : null),
                'size_name' => $custom ? null : $variant?->size?->name,
                'size_type' => $variant?->size?->type,
                'is_custom' => $custom,
                'quantity' => $data['quantity'],
                'unit_price' => $product->price,
                'total' => $product->price * $data['quantity'],
            ]);

            return [$order, $newUser];
        }, 3);

        // Do not authenticate a user whose transaction has been rolled back.
        if ($newUser) {
            Auth::login($newUser, true);
            $request->session()->regenerate();
        }

        session()->push('my_orders', $order->id);

        return redirect()->route('order.thanks', $order);
    }

    public function thanks(Order $order)
    {
        $mine = in_array($order->id, session('my_orders', []), true) || (Auth::id() && Auth::id() === $order->user_id);
        abort_unless($mine, 404);

        return view('site.confirmation', ['order' => $order->load('items.product'), 'fresh' => true]);
    }

    public function track()
    {
        return view('site.track');
    }

    public function find(Request $request)
    {
        $data = $request->validate(['numero' => ['required', 'string'], 'phone' => ['required', 'string']]);
        $order = Order::where('number', strtoupper(trim($data['numero'])))
            ->where('phone', normalize_phone($data['phone']))->first();

        if (! $order) {
            return back()->withInput()->withErrors(['numero' => 'Aucune commande trouvée avec ce numéro et ce téléphone.']);
        }

        return view('site.confirmation', ['order' => $order->load('items.product'), 'fresh' => false]);
    }
}
