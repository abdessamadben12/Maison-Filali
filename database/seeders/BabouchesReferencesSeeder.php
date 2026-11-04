<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catalogue babouches en daim par référence (photos WhatsApp du 01/11/2026) :
 *  - P… ajourées (motif floral découpé) ou lisses ;
 *  - C… à franges.
 * Une fiche produit par référence / couleur, avec sa photo.
 *
 * Commande : php artisan db:seed --class=BabouchesReferencesSeeder
 * Peut être relancée sans risque : rien n'est dupliqué, le stock saisi dans l'admin n'est pas écrasé.
 * Les photos doivent être dans public/uploads/products/.
 */
class BabouchesReferencesSeeder extends Seeder
{
    /** Prix par défaut (DH) — à ajuster dans l'admin si besoin. */
    private const PRIX = 280;

    /** Stock de départ par pointure. */
    private const STOCK = 3;

    private const POINTURES = ['36', '37', '38', '39', '40', '41', '42', '43', '44'];

    /** Couleurs (filtre du catalogue) ajoutées si elles n'existent pas encore. */
    private const HEX = [
        'Noir' => '#1A1A1A', 'Violet' => '#5B3A8C', 'Orange' => '#E0662B', 'Rouge' => '#C0282D',
        'Taupe' => '#8C7B70', 'Turquoise' => '#3FB8C9', 'Moutarde' => '#D9A521', 'Gris' => '#8A939B',
        'Vert anis' => '#9ACD32', 'Cognac' => '#8E4A2B', 'Camel' => '#B9773E', 'Vert' => '#2E9E44',
        'Bleu roi' => '#2546C8', 'Bleu ciel' => '#3A8FD9',
    ];

    private const MODELES = [
        'A' => [
            'nom' => 'Babouches Ajourées',
            'tagline' => 'Babouches en daim :couleur, motif floral ajouré découpé à la main.',
            'description' => "Babouches mules en daim souple, motif floral ajouré découpé à la main et surpiqûres apparentes. Semelle cousue, confortables à la maison comme pour sortir.",
        ],
        'L' => [
            'nom' => 'Babouches Lisses',
            'tagline' => 'Babouches en daim :couleur, ligne épurée et coutures apparentes faites main.',
            'description' => "Babouches mules en daim souple, sans motif, pour une allure sobre et élégante. Surpiqûres apparentes et semelle cousue à la main.",
        ],
        'C' => [
            'nom' => 'Babouches à Franges',
            'tagline' => 'Babouches en daim :couleur à franges, motif fleur ajouré.',
            'description' => "Babouches mules en daim, franges sur l'empeigne et motif fleur découpé à la main. Surpiqûres apparentes et semelle cousue, un classique revisité.",
        ],
    ];

    public function run(): void
    {
        $categorie = Category::firstOrCreate(
            ['slug' => 'babouches'],
            ['name' => 'Babouches', 'description' => 'Babouches en cuir, cousues main.', 'is_active' => true, 'position' => 3],
        );
        $sizes = collect(self::POINTURES)->map(fn ($p) => Size::firstOrCreate(
            ['name' => $p, 'type' => 'pointure'],
            ['position' => 10 + (int) $p - 36],
        ));

        foreach ($this->references() as [$ref, $modele, $nom, $couleur, $photo]) {
            $photo = 'products/'.$photo;

            // P0018 = le produit existant « Babouches Ajourées Fuchsia » : on ajoute seulement la photo
            if ($ref === 'P0018' && ($existant = Product::where('slug', 'babouches-ajourees-fuchsia')->first())) {
                if (! in_array($photo, $existant->images ?? [], true)) {
                    $existant->images = array_values(array_merge($existant->images ?? [], [$photo]));
                    $existant->save();
                }

                continue;
            }

            $m = self::MODELES[$modele];
            $slug = Str::slug($m['nom'].' '.$nom.' '.$ref);
            $product = Product::firstOrNew(['slug' => $slug]);
            if (! $product->exists) {
                $product->fill([
                    'category_id' => $categorie->id,
                    'name' => $m['nom'].' '.$nom,
                    'tagline' => str_replace(':couleur', mb_strtolower($nom), $m['tagline']),
                    'description' => $m['description'].($ref ? "\nRéférence : $ref." : ''),
                    'materials' => "Daim (cuir velours), semelle cousue.\nBrosser avec une brosse à daim. Éviter l'eau.",
                    'price' => self::PRIX,
                    'images' => [$photo],
                    'is_active' => true,
                    'is_new' => true,
                    'is_featured' => false,
                    'is_custom_possible' => false,
                    'position' => (int) Product::max('position') + 1,
                ]);
                $product->save();
            }

            $color = Color::firstOrCreate(
                ['name' => $couleur],
                ['hex' => self::HEX[$couleur] ?? '#999999', 'position' => (int) Color::max('position') + 1],
            );
            foreach ($sizes as $size) {
                $product->variants()->firstOrCreate(
                    ['color_id' => $color->id, 'size_id' => $size->id],
                    ['stock' => self::STOCK],
                );
            }
        }
    }

    /** [référence, modèle (A ajourée / L lisse / C franges), nom de la couleur, couleur du filtre, photo] */
    private function references(): array
    {
        return [
            ['P0032', 'A', 'Terre cuite', 'Cognac', 'babouches-p0032.jpg'],
            ['P0013', 'A', 'Olive', 'Kaki', 'babouches-p0013.jpg'],
            ['P0009', 'A', 'Noir', 'Noir', 'babouches-p0009.jpg'],
            ['P0048', 'A', 'Gris perle', 'Gris', 'babouches-p0048.jpg'],
            ['P0096', 'A', 'Taupe', 'Taupe', 'babouches-p0096.jpg'],
            ['P0039', 'A', 'Moutarde', 'Moutarde', 'babouches-p0039.jpg'],
            ['P0047', 'A', 'Kaki', 'Kaki', 'babouches-p0047.jpg'],
            ['P0024', 'A', 'Bordeaux', 'Bordeaux', 'babouches-p0024.jpg'],
            ['P0025', 'A', 'Aubergine', 'Prune', 'babouches-p0025.jpg'],
            ['P0017', 'A', 'Cognac', 'Camel', 'babouches-p0017.jpg'],
            ['P0027', 'A', 'Bronze', 'Kaki', 'babouches-p0027.jpg'],
            ['P0022', 'A', 'Safran', 'Orange', 'babouches-p0022.jpg'],
            ['P0023', 'A', 'Rouge', 'Rouge', 'babouches-p0023.jpg'],
            ['P0035', 'A', 'Gris', 'Gris', 'babouches-p0035.jpg'],
            ['P0014', 'A', 'Orange', 'Orange', 'babouches-p0014.jpg'],
            ['P0021', 'A', 'Violet', 'Violet', 'babouches-p0021.jpg'],
            ['P0026', 'A', 'Brique', 'Cognac', 'babouches-p0026.jpg'],
            ['P0006', 'A', 'Bleu nuit', 'Nuit', 'babouches-p0006.jpg'],
            ['P0033', 'A', 'Vert sapin', 'Émeraude', 'babouches-p0033.jpg'],
            ['P0031', 'A', 'Ocre', 'Moutarde', 'babouches-p0031.jpg'],
            ['P0028', 'A', 'Bois de rose', 'Rose', 'babouches-p0028.jpg'],
            ['P0015', 'A', 'Paprika', 'Orange', 'babouches-p0015.jpg'],
            ['P0073', 'A', 'Grenat', 'Bordeaux', 'babouches-p0073.jpg'],
            ['P0038', 'A', 'Sable', 'Sable', 'babouches-p0038.jpg'],
            ['P0088', 'A', 'Chocolat', 'Chocolat', 'babouches-p0088.jpg'],
            ['P0004', 'A', 'Nude', 'Sable', 'babouches-p0004.jpg'],
            ['P0034', 'A', 'Brun olive', 'Kaki', 'babouches-p0034.jpg'],
            ['P0094', 'A', 'Rose ancien', 'Rose', 'babouches-p0094.jpg'],
            ['P0092', 'A', 'Bleu roi', 'Bleu roi', 'babouches-p0092.jpg'],
            ['P0091', 'A', 'Vert anis', 'Vert anis', 'babouches-p0091.jpg'],
            ['', 'A', 'Gris clair', 'Gris', 'babouches-gris-clair.jpg'],
            ['P0036', 'A', 'Émeraude', 'Émeraude', 'babouches-p0036.jpg'],
            ['P0046', 'A', 'Menthe', 'Turquoise', 'babouches-p0046.jpg'],
            ['P0018', 'A', 'Fuchsia', 'Fuchsia', 'babouches-p0018.jpg'],
            ['P0029', 'A', 'Noisette', 'Taupe', 'babouches-p0029.jpg'],
            ['P0020', 'L', 'Vert', 'Vert', 'babouches-p0020.jpg'],
            ['P0056', 'L', 'Vert d\'eau', 'Turquoise', 'babouches-p0056.jpg'],
            ['P0093', 'L', 'Noir', 'Noir', 'babouches-p0093.jpg'],
            ['P0019', 'L', 'Framboise', 'Rouge', 'babouches-p0019.jpg'],
            ['P0030', 'L', 'Taupe', 'Taupe', 'babouches-p0030.jpg'],
            ['P0037', 'L', 'Noir profond', 'Noir', 'babouches-p0037.jpg'],
            ['P0044', 'L', 'Tabac', 'Camel', 'babouches-p0044.jpg'],
            ['P0061', 'L', 'Caramel', 'Camel', 'babouches-p0061.jpg'],
            ['P0079', 'L', 'Écru', 'Ivoire', 'babouches-p0079.jpg'],
            ['P0016', 'L', 'Bleu canard', 'Émeraude', 'babouches-p0016.jpg'],
            ['P0071', 'L', 'Beige', 'Sable', 'babouches-p0071.jpg'],
            ['C0033', 'C', 'Bleu indigo', 'Bleu roi', 'babouches-c0033.jpg'],
            ['C0035', 'C', 'Chocolat', 'Chocolat', 'babouches-c0035.jpg'],
            ['C0039', 'C', 'Bleu roi', 'Bleu roi', 'babouches-c0039.jpg'],
            ['C0041', 'C', 'Vert sapin', 'Émeraude', 'babouches-c0041.jpg'],
            ['C0040', 'C', 'Camel', 'Camel', 'babouches-c0040.jpg'],
            ['C0032', 'C', 'Noir', 'Noir', 'babouches-c0032.jpg'],
            ['C0020', 'C', 'Gris ardoise', 'Gris', 'babouches-c0020.jpg'],
            ['C0031', 'C', 'Bleu marine', 'Nuit', 'babouches-c0031.jpg'],
            ['C0038', 'C', 'Taupe', 'Taupe', 'babouches-c0038.jpg'],
            ['C0034', 'C', 'Bleu azur', 'Bleu ciel', 'babouches-c0034.jpg'],
            ['C0030', 'C', 'Kaki', 'Kaki', 'babouches-c0030.jpg'],
            ['C0037', 'C', 'Anthracite', 'Gris', 'babouches-c0037.jpg'],
            ['C0036', 'C', 'Grège', 'Sable', 'babouches-c0036.jpg'],
            ['C0042', 'C', 'Bordeaux', 'Bordeaux', 'babouches-c0042.jpg'],
        ];
    }
}
