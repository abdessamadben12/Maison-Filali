<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Babouches en daim (photos WhatsApp du 01/11/2026) : 3 modèles, chaque couleur = une variante.
 *  - Babouches Ajourées (réf. atelier P…, motif floral découpé) ;
 *  - Babouches Lisses (réf. P…, sans motif) ;
 *  - Babouches à Franges (réf. C…).
 * Chaque photo est liée à sa couleur par son nom de fichier (« …--vert-anis.jpg ») :
 * sur la fiche produit, cliquer une couleur affiche sa photo.
 *
 * Commande : php artisan db:seed --class=BabouchesReferencesSeeder
 * Peut être relancée sans risque. Supprime aussi les anciennes fiches « une fiche par référence »
 * créées par la version précédente de ce fichier (désactivées seulement si elles ont des commandes).
 */
class BabouchesReferencesSeeder extends Seeder
{
    /** Prix par défaut (DH) — à ajuster dans l'admin si besoin. */
    private const PRIX = 280;

    /** Stock de départ par couleur et par pointure. */
    private const STOCK = 3;

    private const POINTURES = ['36', '37', '38', '39', '40', '41', '42', '43', '44'];

    /** Teinte des couleurs créées si elles n'existent pas encore (les couleurs existantes gardent la leur). */
    private const HEX = [
        'Noir' => '#1A1A1A', 'Violet' => '#5B3A8C', 'Orange' => '#E0662B', 'Rouge' => '#C0282D',
        'Taupe' => '#8C7B70', 'Moutarde' => '#D9A521', 'Gris' => '#8A939B', 'Vert anis' => '#9ACD32',
        'Cognac' => '#B5702E', 'Camel' => '#B9773E', 'Vert' => '#2E9E44', 'Bleu roi' => '#2546C8',
        'Terre cuite' => '#A0522D', 'Olive' => '#8A7A45', 'Gris perle' => '#B8B2A6', 'Aubergine' => '#6B2F45',
        'Bronze' => '#7A5C30', 'Safran' => '#E07A10', 'Brique' => '#8B3A2E', 'Vert sapin' => '#1F4A40',
        'Ocre' => '#C08A2E', 'Bois de rose' => '#9A4E55', 'Paprika' => '#C8432A', 'Grenat' => '#7A1E35',
        'Nude' => '#D4A882', 'Brun olive' => '#6F5A35', 'Gris clair' => '#B5ACA2', 'Menthe' => '#5FB3A8',
        'Noisette' => '#8A6A4C', "Vert d'eau" => '#4FA39A', 'Framboise' => '#B0153E', 'Tabac' => '#9A7A55',
        'Caramel' => '#A0652E', 'Bleu canard' => '#1A5A63', 'Bleu azur' => '#2A86E0', 'Bleu indigo' => '#2E3F8F',
        'Gris ardoise' => '#4A4E5C', 'Anthracite' => '#3C3B3D', 'Grège' => '#B8A898',
        'Bordeaux' => '#6E1424', 'Chocolat' => '#4A2A22', 'Nuit' => '#1E2F5C', 'Sable' => '#C9B79A',
        'Kaki' => '#4B4A3F', 'Rose' => '#E7958A', 'Fuchsia' => '#D12F5A', 'Émeraude' => '#0F4A3E', 'Ivoire' => '#F1E8D6',
    ];

    public function run(): void
    {
        $this->supprimerAnciennesFiches();

        $categorie = Category::firstOrCreate(
            ['slug' => 'babouches'],
            ['name' => 'Babouches', 'description' => 'Babouches en cuir, cousues main.', 'is_active' => true, 'position' => 3],
        );
        $sizes = collect(self::POINTURES)->map(fn ($p) => Size::firstOrCreate(
            ['name' => $p, 'type' => 'pointure'],
            ['position' => 10 + (int) $p - 36],
        ));

        foreach ($this->modeles() as $m) {
            $photos = collect($m['couleurs'])->map(fn ($c) => 'products/'.$c[2])->values()->all();

            $product = Product::firstOrNew(['slug' => $m['slug']]);
            if (! $product->exists) {
                $product->fill([
                    'category_id' => $categorie->id,
                    'name' => $m['nom'],
                    'tagline' => $m['tagline'],
                    'description' => $m['description'],
                    'materials' => "Daim (cuir velours), semelle cousue.\nBrosser avec une brosse à daim. Éviter l'eau.",
                    'price' => self::PRIX,
                    'is_active' => true,
                    'is_new' => true,
                    'is_featured' => true,
                    'is_custom_possible' => false,
                    'position' => (int) Product::max('position') + 1,
                ]);
            }
            // Ajoute les photos manquantes (garde celles ajoutées depuis l'admin)
            $product->images = array_values(array_unique(array_merge($product->images ?? [], $photos)));
            $product->save();

            foreach ($m['couleurs'] as [$ref, $nom]) {
                $color = Color::firstOrCreate(
                    ['name' => $nom],
                    ['hex' => self::HEX[$nom] ?? '#999999', 'position' => (int) Color::max('position') + 1],
                );
                foreach ($sizes as $size) {
                    $product->variants()->firstOrCreate(
                        ['color_id' => $color->id, 'size_id' => $size->id],
                        ['stock' => self::STOCK],
                    );
                }
            }
        }
    }

    /** Anciennes fiches « une par référence » (ex. babouches-ajourees-moutarde-p0039). */
    private function supprimerAnciennesFiches(): void
    {
        $anciennes = Product::where('slug', 'like', 'babouches-%')->get()->filter(
            fn ($p) => preg_match('/^babouches-(ajourees|lisses|a-franges)-.+-[pc]\d{4}$/', $p->slug)
                || $p->slug === 'babouches-ajourees-gris-clair'
        );
        foreach ($anciennes as $p) {
            $p->orderItems()->exists() ? $p->update(['is_active' => false]) : $p->delete();
        }

        // La photo P0018 avait été ajoutée au produit « Babouches Ajourées Fuchsia » : elle est maintenant dans le modèle ajouré
        if ($f = Product::where('slug', 'babouches-ajourees-fuchsia')->first()) {
            $images = array_values(array_diff($f->images ?? [], ['products/babouches-p0018.jpg']));
            if ($images !== array_values($f->images ?? [])) {
                $f->images = $images;
                $f->save();
            }
        }

        // « Babouches en Daim » (ajout précédent) : photos renommées pour être liées à leur couleur
        if ($d = Product::where('slug', 'babouches-en-daim')->first()) {
            $renommage = [
            'products/babouches-daim-rose-1.jpg' => 'products/babouches-daim--rose.jpg',
            'products/babouches-daim-couleurs-2.jpg' => 'products/babouches-daim-couleurs.jpg',
            'products/babouches-daim-noir-3.jpg' => 'products/babouches-daim--noir.jpg',
            'products/babouches-daim-noir-4.jpg' => 'products/babouches-daim-noir-2.jpg',
            'products/babouches-daim-violet-5.jpg' => 'products/babouches-daim--violet.jpg',
            'products/babouches-daim-sable-6.jpg' => 'products/babouches-daim--sable.jpg',
            'products/babouches-daim-ivoire-7.jpg' => 'products/babouches-daim--ivoire.jpg',
            'products/babouches-daim-atelier-8.jpg' => 'products/babouches-daim-atelier.jpg',
            ];
            $images = array_map(fn ($i) => $renommage[$i] ?? $i, $d->images ?? []);
            if ($images !== ($d->images ?? [])) {
                $d->images = $images;
                $d->save();
            }
        }
    }

    /** [réf. atelier, couleur, photo] — la référence sert de repère, elle n'est pas affichée sur le site. */
    private function modeles(): array
    {
        return [
            [
                'slug' => 'babouches-ajourees-en-daim',
                'nom' => 'Babouches Ajourées en Daim',
                'tagline' => 'Babouches en daim souple, motif floral ajouré découpé à la main. 35 couleurs.',
                'description' => "Babouches mules en daim souple, motif floral ajouré découpé à la main et surpiqûres apparentes. Semelle cousue, confortables à la maison comme pour sortir. Choisissez votre couleur parmi 35 teintes.",
                'couleurs' => [
                ['P0039', 'Moutarde', 'babouches-ajourees--moutarde.jpg'],
                ['P0032', 'Terre cuite', 'babouches-ajourees--terre-cuite.jpg'],
                ['P0013', 'Olive', 'babouches-ajourees--olive.jpg'],
                ['P0009', 'Noir', 'babouches-ajourees--noir.jpg'],
                ['P0048', 'Gris perle', 'babouches-ajourees--gris-perle.jpg'],
                ['P0096', 'Taupe', 'babouches-ajourees--taupe.jpg'],
                ['P0047', 'Kaki', 'babouches-ajourees--kaki.jpg'],
                ['P0024', 'Bordeaux', 'babouches-ajourees--bordeaux.jpg'],
                ['P0025', 'Aubergine', 'babouches-ajourees--aubergine.jpg'],
                ['P0017', 'Cognac', 'babouches-ajourees--cognac.jpg'],
                ['P0027', 'Bronze', 'babouches-ajourees--bronze.jpg'],
                ['P0022', 'Safran', 'babouches-ajourees--safran.jpg'],
                ['P0023', 'Rouge', 'babouches-ajourees--rouge.jpg'],
                ['P0035', 'Gris', 'babouches-ajourees--gris.jpg'],
                ['P0014', 'Orange', 'babouches-ajourees--orange.jpg'],
                ['P0021', 'Violet', 'babouches-ajourees--violet.jpg'],
                ['P0026', 'Brique', 'babouches-ajourees--brique.jpg'],
                ['P0006', 'Nuit', 'babouches-ajourees--nuit.jpg'],
                ['P0033', 'Vert sapin', 'babouches-ajourees--vert-sapin.jpg'],
                ['P0031', 'Ocre', 'babouches-ajourees--ocre.jpg'],
                ['P0028', 'Bois de rose', 'babouches-ajourees--bois-de-rose.jpg'],
                ['P0015', 'Paprika', 'babouches-ajourees--paprika.jpg'],
                ['P0073', 'Grenat', 'babouches-ajourees--grenat.jpg'],
                ['P0038', 'Sable', 'babouches-ajourees--sable.jpg'],
                ['P0088', 'Chocolat', 'babouches-ajourees--chocolat.jpg'],
                ['P0004', 'Nude', 'babouches-ajourees--nude.jpg'],
                ['P0034', 'Brun olive', 'babouches-ajourees--brun-olive.jpg'],
                ['P0094', 'Rose', 'babouches-ajourees--rose.jpg'],
                ['P0092', 'Bleu roi', 'babouches-ajourees--bleu-roi.jpg'],
                ['P0091', 'Vert anis', 'babouches-ajourees--vert-anis.jpg'],
                ['P0024 ?', 'Gris clair', 'babouches-ajourees--gris-clair.jpg'],
                ['P0036', 'Émeraude', 'babouches-ajourees--emeraude.jpg'],
                ['P0046', 'Menthe', 'babouches-ajourees--menthe.jpg'],
                ['P0018', 'Fuchsia', 'babouches-ajourees--fuchsia.jpg'],
                ['P0029', 'Noisette', 'babouches-ajourees--noisette.jpg'],
                ],
            ],
            [
                'slug' => 'babouches-lisses-en-daim',
                'nom' => 'Babouches Lisses en Daim',
                'tagline' => 'Babouches en daim, ligne épurée et coutures apparentes faites main.',
                'description' => "Babouches mules en daim souple, sans motif, pour une allure sobre et élégante. Surpiqûres apparentes et semelle cousue à la main.",
                'couleurs' => [
                ['P0020', 'Vert', 'babouches-lisses--vert.jpg'],
                ['P0056', 'Vert d\'eau', 'babouches-lisses--vert-deau.jpg'],
                ['P0093', 'Noir', 'babouches-lisses--noir.jpg'],
                ['P0037', 'Noir', 'babouches-lisses-noir-2.jpg'],
                ['P0019', 'Framboise', 'babouches-lisses--framboise.jpg'],
                ['P0030', 'Taupe', 'babouches-lisses--taupe.jpg'],
                ['P0044', 'Tabac', 'babouches-lisses--tabac.jpg'],
                ['P0061', 'Caramel', 'babouches-lisses--caramel.jpg'],
                ['P0079', 'Ivoire', 'babouches-lisses--ivoire.jpg'],
                ['P0016', 'Bleu canard', 'babouches-lisses--bleu-canard.jpg'],
                ['P0071', 'Sable', 'babouches-lisses--sable.jpg'],
                ],
            ],
            [
                'slug' => 'babouches-a-franges-en-daim',
                'nom' => 'Babouches à Franges en Daim',
                'tagline' => 'Babouches en daim à franges, motif fleur ajouré découpé à la main.',
                'description' => "Babouches mules en daim, franges sur l'empeigne et motif fleur découpé à la main. Surpiqûres apparentes et semelle cousue, un classique revisité.",
                'couleurs' => [
                ['C0034', 'Bleu azur', 'babouches-a-franges--bleu-azur.jpg'],
                ['C0033', 'Bleu indigo', 'babouches-a-franges--bleu-indigo.jpg'],
                ['C0035', 'Chocolat', 'babouches-a-franges--chocolat.jpg'],
                ['C0039', 'Bleu roi', 'babouches-a-franges--bleu-roi.jpg'],
                ['C0041', 'Vert sapin', 'babouches-a-franges--vert-sapin.jpg'],
                ['C0040', 'Camel', 'babouches-a-franges--camel.jpg'],
                ['C0032', 'Noir', 'babouches-a-franges--noir.jpg'],
                ['C0020', 'Gris ardoise', 'babouches-a-franges--gris-ardoise.jpg'],
                ['C0031', 'Nuit', 'babouches-a-franges--nuit.jpg'],
                ['C0038', 'Taupe', 'babouches-a-franges--taupe.jpg'],
                ['C0030', 'Kaki', 'babouches-a-franges--kaki.jpg'],
                ['C0037', 'Anthracite', 'babouches-a-franges--anthracite.jpg'],
                ['C0036', 'Grège', 'babouches-a-franges--grege.jpg'],
                ['C0042', 'Bordeaux', 'babouches-a-franges--bordeaux.jpg'],
                ],
            ],
        ];
    }
}
