<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Support\Pages;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    /** Plan du site pour Google (mis en cache, régénéré quand un produit ou une catégorie change). */
    public function sitemap()
    {
        $xml = Cache::store('file')->remember('sitemap.v2', now()->addHours(12), function () {
            $urls = [
                ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
                ['loc' => route('catalogue'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ];
            foreach (Category::active()->get() as $cat) {
                $urls[] = ['loc' => route('category', $cat), 'lastmod' => $cat->updated_at, 'priority' => '0.8', 'changefreq' => 'weekly'];
            }
            foreach (Product::active()->orderBy('position')->get() as $p) {
                $urls[] = ['loc' => route('product', $p), 'lastmod' => $p->updated_at, 'priority' => '0.8', 'changefreq' => 'weekly',
                    'images' => array_map(fn ($u) => ['loc' => $u, 'title' => $p->name], $p->original_image_urls)];
            }
            $urls[] = ['loc' => route('contact'), 'priority' => '0.6', 'changefreq' => 'monthly'];
            foreach (Pages::slugs() as $slug) {
                $urls[] = ['loc' => route('page', $slug), 'priority' => $slug === 'notre-histoire' ? '0.6' : '0.3', 'changefreq' => 'monthly'];
            }

            return view('seo.sitemap', ['urls' => $urls])->render();
        });

        return $this->clean(ltrim($xml), 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /mon-compte',
            'Disallow: /connexion',
            'Disallow: /inscription',
            'Disallow: /commande',
            'Disallow: /suivre-ma-commande',
            'Disallow: /*?*q=',
            'Disallow: /*?*tri=',
            '',
            'Sitemap: '.url('sitemap.xml'),
        ];
        // Site de test / préproduction : SHOP_NOINDEX=true dans .env pour tout bloquer
        if (config('shop.noindex')) {
            $lines = ['User-agent: *', 'Disallow: /', '', 'Sitemap: '.url('sitemap.xml')];
        }

        return $this->clean(implode("\n", $lines)."\n", 'text/plain; charset=UTF-8');
    }

    /**
     * Réponse « propre » : si un fichier PHP a affiché un espace ou une ligne vide par erreur
     * (souvent avant « <?php » ou après « ?> »), on l'efface pour que le XML commence bien par <?xml.
     */
    private function clean(string $body, string $type)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response($body, 200, ['Content-Type' => $type, 'X-Robots-Tag' => 'noindex']);
    }
}
