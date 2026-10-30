<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Commandes de démonstration (noms repris de la maquette) pour présenter l'admin au client.
 * Commande : php artisan db:seed --class=DemoOrdersSeeder
 */
class DemoOrdersSeeder extends Seeder
{
    public function run(): void
    {
        $orders = [
            // [nom, téléphone, ville, adresse, produit, couleur, taille, qté, statut, il y a (minutes)]
            ['Omar Bennani', '0633214567', 'Agadir', 'Hay Salam, rue 12', 'Babouches Filali Bordeaux', 'Bordeaux', '43', 1, 'livree', 9000],
            ['Khadija Ouazzani', '0648765432', 'Fès', 'Route d’Imouzzer', 'Caftan Velours Lalla', 'Émeraude', 'S', 1, 'expediee', 2000],
            ['Nadia Berrada', '0655098712', 'Tanger', 'Boulevard Pasteur', 'Babouches Ajourées Rose', 'Rose', '37', 2, 'confirmee', 1500],
            ['Youssef Alami', '0670112290', 'Marrakech', 'Guéliz, rue de la Liberté', 'Babouches Ajourées Fuchsia', 'Fuchsia', '42', 1, 'en_attente', 180],
            ['Salma Benali', '0612345678', 'Casablanca', 'Bd Zerktouni, résidence Al Amal, appt 12', 'Caftan Velours Lalla', 'Bordeaux', 'M', 1, 'en_attente', 60],
            ['Imane Tazi', '0661223344', 'Rabat', 'Agdal, avenue de France', 'Babouches Filali Bordeaux', 'Bordeaux', '39', 1, 'en_attente', 12],
        ];

        foreach ($orders as [$name, $phone, $city, $address, $productName, $color, $size, $qty, $status, $ago]) {
            $product = Product::with('variants.color', 'variants.size')->where('name', $productName)->firstOrFail();
            $variant = $product->variants->first(fn ($v) => $v->color?->name === $color && $v->size?->name === $size);
            $at = now()->subMinutes($ago);

            $customer = Customer::updateOrCreate(['phone' => $phone], ['name' => $name, 'city' => $city, 'address' => $address]);
            $order = Order::create([
                'customer_id' => $customer->id, 'customer_name' => $name, 'phone' => $phone, 'city' => $city,
                'address' => $address, 'status' => 'en_attente', 'total' => $product->price * $qty,
            ]);
            $order->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
            $order->update(['number' => sprintf('MF-%s-%04d', $at->format('Y'), $order->id)]);
            $order->items()->create([
                'product_id' => $product->id, 'product_variant_id' => $variant?->id, 'product_name' => $product->name,
                'color_name' => $color, 'size_name' => $size, 'size_type' => $variant?->size?->type,
                'quantity' => $qty, 'unit_price' => $product->price, 'total' => $product->price * $qty,
            ]);

            $order->load('items.variant');
            if (in_array($status, ['confirmee', 'expediee', 'livree'])) {
                // le stock de départ inclut déjà ces pièces : on le remet avant de confirmer
                $variant?->increment('stock', $qty);
                $order->confirm();
                $order->forceFill(['confirmed_at' => $at->copy()->addHours(2)])->saveQuietly();
            }
            if (in_array($status, ['expediee', 'livree'])) {
                $order->ship();
                $order->forceFill(['shipped_at' => $at->copy()->addDay()])->saveQuietly();
            }
            if ($status === 'livree') {
                $order->deliver();
                $order->forceFill(['delivered_at' => $at->copy()->addDays(3)])->saveQuietly();
            }
        }
    }
}
