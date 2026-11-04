<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Database\Seeder;

/**
 * Ajout des nouveaux produits (photos WhatsApp du 01/11/2026).
 * Commande : php artisan db:seed --class=NouveauxProduitsSeeder
 *
 * Sans danger : peut être relancé plusieurs fois (rien n'est dupliqué,
 * le stock déjà saisi dans l'admin n'est jamais écrasé).
 * Les photos doivent être dans public/uploads/products/.
 */
class NouveauxProduitsSeeder extends Seeder
{
    /** Prix par défaut (DH) — à ajuster dans l'admin si besoin. */
    private const PRIX = 280;

    /** Stock de départ par couleur et par pointure. */
    private const STOCK = 3;

    /** Pointures proposées. */
    private const POINTURES = ['36', '37', '38', '39', '40', '41'];

    public function run(): void
    {
        $babouches = Category::firstOrCreate(
            ['slug' => 'babouches'],
            ['name' => 'Babouches', 'description' => 'Babouches en cuir, cousues main.', 'is_active' => true, 'position' => 3],
        );

        // 1) Babouches en daim — 8 couleurs
        $this->produit($babouches, [
            'name' => 'Babouches en Daim',
            'slug' => 'babouches-en-daim',
            'tagline' => 'Babouches en daim souple, coutures apparentes faites main, semelle confortable.',
            'description' => "Babouches en daim doux au toucher, bout arrondi et surpiqûres apparentes cousues à la main. "
                ."Se portent fermées ou talon rabattu, à la maison comme pour sortir. Disponibles en huit couleurs.",
            'materials' => "Daim (cuir velours), semelle cousue.\nBrosser avec une brosse à daim. Éviter l'eau.",
            'images' => [
                'products/babouches-daim--rose.jpg',
                'products/babouches-daim-couleurs.jpg',
                'products/babouches-daim--noir.jpg',
                'products/babouches-daim-noir-2.jpg',
                'products/babouches-daim--violet.jpg',
                'products/babouches-daim--sable.jpg',
                'products/babouches-daim--ivoire.jpg',
                'products/babouches-daim-atelier.jpg',
            ],
            'is_new' => true,
            'is_featured' => true,
        ], ['Rose', 'Noir', 'Violet', 'Sable', 'Ivoire', 'Orange', 'Rouge', 'Taupe']);

        // 2) Mocassins ajourés en daim (vidéo) — 10 couleurs
        $this->produit($babouches, [
            'name' => 'Mocassins Ajourés en Daim',
            'slug' => 'mocassins-ajoures-en-daim',
            'tagline' => 'Mocassins en daim perforé, légers et respirants, pour tous les jours.',
            'description' => "Mocassins souples en daim ajouré : perforations sur l'empeigne pour plus de fraîcheur, "
                ."surpiqûres faites main et semelle souple. Une large palette de couleurs pour accorder avec toutes vos tenues.",
            'materials' => "Daim perforé, semelle souple.\nBrosser avec une brosse à daim. Éviter l'eau.",
            'images' => [
                'products/mocassins-ajoures-daim-1.jpg',
                'products/mocassins-ajoures-daim-2.jpg',
                'products/mocassins-ajoures-daim-3.jpg',
            ],
            'is_new' => true,
            'is_featured' => false,
        ], ['Nuit', 'Turquoise', 'Moutarde', 'Bordeaux', 'Kaki', 'Sable', 'Orange', 'Rose', 'Gris', 'Vert anis']);

        // 3) Nouvelle photo pour le produit existant « Babouches Ajourées Fuchsia » (mise en premier)
        $fuchsia = Product::where('slug', 'babouches-ajourees-fuchsia')->first();
        $photo = 'products/babouches-ajourees-fuchsia-2.jpg';
        if ($fuchsia && ! in_array($photo, $fuchsia->images ?? [], true)) {
            $fuchsia->images = array_values(array_merge([$photo], $fuchsia->images ?? []));
            $fuchsia->save();
        }
    }

    /** @param array<string, mixed> $data  @param array<int, string> $couleurs */
    private function produit(Category $categorie, array $data, array $couleurs): void
    {
        $product = Product::firstOrNew(['slug' => $data['slug']]);
        if (! $product->exists) {
            $product->fill($data + [
                'category_id' => $categorie->id,
                'price' => self::PRIX,
                'is_active' => true,
                'is_custom_possible' => false,
                'position' => (int) Product::max('position') + 1,
            ]);
            $product->save();
        }

        foreach ($couleurs as $nom) {
            $color = $this->couleur($nom);
            foreach (self::POINTURES as $p) {
                $size = Size::firstOrCreate(['name' => $p, 'type' => 'pointure'], ['position' => 10 + (int) $p - 36]);
                $product->variants()->firstOrCreate(
                    ['color_id' => $color->id, 'size_id' => $size->id],
                    ['stock' => self::STOCK],
                );
            }
        }
    }

    private function couleur(string $nom): Color
    {
        $hex = [
            'Noir' => '#1A1A1A', 'Violet' => '#5B3A8C', 'Orange' => '#E0662B', 'Rouge' => '#C0282D',
            'Taupe' => '#8C7B70', 'Turquoise' => '#3FB8C9', 'Moutarde' => '#D9A521', 'Gris' => '#8A939B',
            'Vert anis' => '#9ACD32',
        ];

        return Color::firstOrCreate(
            ['name' => $nom],
            ['hex' => $hex[$nom] ?? '#999999', 'position' => (int) Color::max('position') + 1],
        );
    }
}
