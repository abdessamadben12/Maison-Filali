<?php

namespace Database\Seeders;

use App\Models\Color;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Supprime tous les produits ajoutés par NouveauxProduitsSeeder et BabouchesReferencesSeeder
 * (les deux versions), retire les photos ajoutées au produit « Babouches Ajourées Fuchsia »
 * et supprime les couleurs créées qui ne servent plus à aucun produit.
 *
 * Commande : php artisan db:seed --class=SupprimerProduitsAjoutesSeeder
 *
 * Les commandes déjà passées sont conservées : elles gardent le nom du produit, de la couleur et de la taille.
 */
class SupprimerProduitsAjoutesSeeder extends Seeder
{
    private const SLUGS = [
        'babouches-en-daim',
        'mocassins-ajoures-en-daim',
        'babouches-ajourees-en-daim',
        'babouches-lisses-en-daim',
        'babouches-a-franges-en-daim',
        'babouches-ajourees-gris-clair',
    ];

    /** Photos ajoutées au produit existant « Babouches Ajourées Fuchsia ». */
    private const PHOTOS_FUCHSIA = [
        'products/babouches-ajourees-fuchsia-2.jpg',
        'products/babouches-p0018.jpg',
    ];

    /** Couleurs créées par les seeders (supprimées seulement si plus aucun produit ne les utilise). */
    private const COULEURS = [
        'Noir', 'Violet', 'Orange', 'Rouge', 'Taupe', 'Turquoise', 'Moutarde', 'Gris', 'Vert anis',
        'Cognac', 'Camel', 'Vert', 'Bleu roi', 'Bleu ciel', 'Terre cuite', 'Olive', 'Gris perle',
        'Aubergine', 'Bronze', 'Safran', 'Brique', 'Vert sapin', 'Ocre', 'Bois de rose', 'Paprika',
        'Grenat', 'Nude', 'Brun olive', 'Gris clair', 'Menthe', 'Noisette', "Vert d'eau", 'Framboise',
        'Tabac', 'Caramel', 'Bleu canard', 'Bleu azur', 'Bleu indigo', 'Gris ardoise', 'Anthracite', 'Grège',
    ];

    public function run(): void
    {
        // Produits ajoutés + anciennes fiches « une par référence » (ex. babouches-ajourees-moutarde-p0039)
        $produits = Product::where('slug', 'like', 'babouches-%')->orWhere('slug', 'like', 'mocassins-%')->get()
            ->filter(fn ($p) => in_array($p->slug, self::SLUGS, true)
                || preg_match('/^babouches-(ajourees|lisses|a-franges)-.+-[pc]\d{4}$/', $p->slug));

        foreach ($produits as $p) {
            $this->command?->line('Supprimé : '.$p->name);
            $p->delete();
        }

        if ($f = Product::where('slug', 'babouches-ajourees-fuchsia')->first()) {
            $images = array_values(array_diff($f->images ?? [], self::PHOTOS_FUCHSIA));
            if ($images !== array_values($f->images ?? [])) {
                $f->images = $images;
                $f->save();
                $this->command?->line('Photos retirées de : '.$f->name);
            }
        }

        $couleurs = Color::whereIn('name', self::COULEURS)->whereDoesntHave('variants')->get();
        foreach ($couleurs as $c) {
            $c->delete();
        }

        $this->command?->info($produits->count().' produit(s) et '.$couleurs->count().' couleur(s) supprimé(s).');
    }
}
