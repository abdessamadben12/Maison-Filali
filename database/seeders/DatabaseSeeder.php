<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Catalogue de départ (repris de la maquette Figma) + compte administrateur.
     * Commande : php artisan migrate:fresh --seed
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@maisonfilali.ma'], [
            'name' => 'Admin Maison Filali',
            'password' => env('ADMIN_PASSWORD', 'MaisonFilali2026!'),
            'is_admin' => true,
            'role' => 'gerant',
        ]);

        // Couleurs
        $colors = collect([
            ['Bordeaux', '#6E1424'], ['Émeraude', '#0F4A3E'], ['Chocolat', '#4A2A22'], ['Prune', '#3D2347'],
            ['Nuit', '#1E2F5C'], ['Ivoire', '#F1E8D6'], ['Sable', '#C9B79A'], ['Kaki', '#4B4A3F'],
            ['Rose', '#E7958A'], ['Fuchsia', '#D12F5A'],
        ])->mapWithKeys(fn ($c, $i) => [$c[0] => Color::create(['name' => $c[0], 'hex' => $c[1], 'position' => $i])]);

        // Tailles vêtements + pointures
        $sizes = collect();
        foreach ([['S', 'Tour de poitrine 84 – 88 cm'], ['M', 'Tour de poitrine 89 – 94 cm'], ['L', 'Tour de poitrine 95 – 100 cm'], ['XL', 'Tour de poitrine 101 – 106 cm'], ['XXL', 'Tour de poitrine 107 – 112 cm']] as $i => [$n, $g]) {
            $sizes[$n] = Size::create(['name' => $n, 'type' => 'vetement', 'guide' => $g, 'position' => $i]);
        }
        foreach (range(36, 44) as $i => $p) {
            $sizes[(string) $p] = Size::create(['name' => (string) $p, 'type' => 'pointure', 'guide' => number_format(22.5 + ($p - 36) * 0.65, 1, ',', '').' cm', 'position' => 10 + $i]);
        }

        // Catégories
        $cats = collect([
            ['Caftans', 'Caftans en velours, brodés main, et créations sur mesure.', 'categories/caftans.jpg', true],
            ['Takchitas', 'Takchitas de cérémonie, brodées et ceinturées.', 'categories/takchitas.jpg', false],
            ['Djellabas', 'Djellabas élégantes pour tous les jours et les fêtes.', 'categories/djellabas.jpg', false],
            ['Babouches', 'Babouches en cuir, cousues main.', 'categories/babouches.jpg', true],
        ])->mapWithKeys(fn ($c, $i) => [$c[0] => Category::create([
            'name' => $c[0], 'description' => $c[1], 'image' => $c[2], 'in_menu' => $c[3], 'position' => $i,
        ])]);

        $make = function (array $p, array $stock) use ($colors, $sizes) {
            $product = Product::create($p);
            foreach ($stock as $color => $bySize) {
                foreach ($bySize as $size => $qty) {
                    $product->variants()->create([
                        'color_id' => $color === '-' ? null : $colors[$color]->id,
                        'size_id' => $size === '-' ? null : $sizes[(string) $size]->id,
                        'stock' => $qty,
                    ]);
                }
            }

            return $product;
        };

        $make([
            'category_id' => $cats['Caftans']->id, 'name' => 'Caftan Velours Lalla', 'price' => 1650,
            'tagline' => "Velours profond, encolure brodée main et collier de perles nacrées. Une pièce confortable et raffinée pour recevoir ou pour les soirées d'hiver.",
            'description' => 'Caftan en velours, coupe ample et manches évasées. Encolure et plastron brodés à la main, finition sfifa ton sur ton. Collier de perles offert.',
            'materials' => "Velours de soie et viscose. Broderie et sfifa faites main à l'atelier.\nNettoyage à sec uniquement. Repassage doux sur l'envers.",
            'images' => ['products/caftan-velours-lalla-1.jpg', 'products/caftan-velours-lalla-2.jpg', 'products/caftan-velours-lalla-3.jpg', 'products/caftan-velours-lalla-4.jpg'],
            'is_new' => true, 'is_featured' => true, 'is_custom_possible' => true, 'position' => 1,
        ], [
            'Bordeaux' => ['S' => 2, 'M' => 4, 'L' => 5, 'XL' => 3, 'XXL' => 0],
            'Émeraude' => ['S' => 3, 'M' => 5, 'L' => 4, 'XL' => 3],
            'Chocolat' => ['M' => 4, 'L' => 3, 'XL' => 3],
            'Prune' => ['S' => 3, 'M' => 4, 'L' => 1],
            'Nuit' => ['S' => 3, 'M' => 5, 'L' => 4, 'XL' => 3],
        ]);

        $make([
            'category_id' => $cats['Caftans']->id, 'name' => 'Caftan Émeraude Brodé', 'price' => 2900,
            'tagline' => 'Caftan de fête en crêpe, plastron brodé de fil doré et boutonnière aakad faite main.',
            'description' => 'Coupe droite fluide, manches longues, broderie dorée sur le plastron et les poignets. Livré avec sa ceinture assortie.',
            'materials' => "Crêpe de soie, fil doré, aakad en soie.\nNettoyage à sec uniquement.",
            'images' => ['products/caftan-emeraude-brode-1.jpg'],
            'is_new' => true, 'is_custom_possible' => true, 'position' => 2,
        ], [
            'Émeraude' => ['S' => 3, 'M' => 4, 'L' => 4, 'XL' => 3],
            'Bordeaux' => ['M' => 3, 'L' => 3],
            'Nuit' => ['S' => 3, 'M' => 3],
        ]);

        $make([
            'category_id' => $cats['Takchitas']->id, 'name' => 'Takchita Bordeaux Royale', 'price' => 4200,
            'tagline' => 'Takchita deux pièces pour les grandes occasions : dfina en mousseline brodée et ceinture mdama.',
            'description' => 'Tahtia en satin duchesse, dfina en mousseline brodée main, finitions sfifa et aakad. Ceinture mdama incluse.',
            'materials' => "Satin duchesse, mousseline de soie, broderie main.\nNettoyage à sec uniquement.",
            'images' => ['products/takchita-bordeaux-royale-1.jpg'],
            'is_new' => true, 'is_custom_possible' => true, 'position' => 3,
        ], [
            'Bordeaux' => ['S' => 3, 'M' => 3, 'L' => 3],
            'Émeraude' => ['M' => 3, 'L' => 3],
        ]);

        $make([
            'category_id' => $cats['Djellabas']->id, 'name' => 'Djellaba Ivoire à Capuche', 'price' => 1450,
            'tagline' => 'Djellaba légère à capuche, finitions sfifa ton sur ton, pour le quotidien comme pour les fêtes.',
            'description' => 'Coupe ample et confortable, capuche traditionnelle, manches droites. Boutonnière aakad sur le devant.',
            'materials' => "Crêpe fluide, sfifa en soie.\nLavage délicat à 30 °C ou nettoyage à sec.",
            'images' => ['products/djellaba-ivoire-1.jpg'],
            'position' => 4,
        ], [
            'Ivoire' => ['M' => 4, 'L' => 5, 'XL' => 3],
            'Sable' => ['M' => 3, 'L' => 4],
            'Kaki' => ['M' => 3, 'XL' => 3],
        ]);

        $make([
            'category_id' => $cats['Babouches']->id, 'name' => 'Babouches Filali Bordeaux', 'price' => 280,
            'tagline' => 'Babouches en cuir bordeaux, motif ajouré et semelle cousue main.',
            'description' => 'Babouches traditionnelles en cuir, motif ajouré découpé à la main, semelle cousue.',
            'materials' => "Cuir.\nNettoyer avec un chiffon sec. Éviter l'eau.",
            'images' => ['products/babouches-filali-bordeaux-1.jpg', 'products/babouches-filali-bordeaux-2.jpg'],
            'is_new' => true, 'position' => 5,
        ], ['Bordeaux' => ['36' => 4, '37' => 5, '38' => 6, '39' => 5, '40' => 5, '41' => 4, '42' => 3, '43' => 3, '44' => 3]]);

        $make([
            'category_id' => $cats['Babouches']->id, 'name' => 'Babouches Ajourées Rose', 'price' => 280,
            'tagline' => 'Babouches ajourées en cuir rose poudré, légères et confortables.',
            'description' => 'Cuir souple teint rose, motif géométrique ajouré, coutures apparentes faites main.',
            'materials' => "Cuir teint.\nNettoyer avec un chiffon sec.",
            'images' => ['products/babouches-ajourees-rose-1.png'],
            'position' => 6,
        ], ['Rose' => ['36' => 3, '37' => 5, '38' => 0, '39' => 4, '40' => 3, '41' => 3]]);

        $make([
            'category_id' => $cats['Babouches']->id, 'name' => 'Babouches Ajourées Fuchsia', 'price' => 280,
            'tagline' => 'Babouches ajourées en cuir fuchsia, la touche de couleur de vos tenues.',
            'description' => 'Cuir souple teint fuchsia, motif géométrique ajouré, coutures apparentes faites main.',
            'materials' => "Cuir teint.\nNettoyer avec un chiffon sec.",
            'images' => ['products/babouches-ajourees-fuchsia-1.png'],
            'position' => 7,
        ], ['Fuchsia' => ['36' => 3, '37' => 4, '38' => 5, '39' => 4, '40' => 4, '41' => 3, '42' => 2, '43' => 3, '44' => 3]]);
    }
}
