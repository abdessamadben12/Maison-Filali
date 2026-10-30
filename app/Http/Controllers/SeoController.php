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
        $xml = Cache::remember('sitemap.xml', now()->addHours(12), function () {
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

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
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
        // Hors production (test, préproduction) : ne rien indexer
        if (! app()->isProduction()) {
            $lines = ['User-agent: *', 'Disallow: /', '', 'Sitemap: '.url('sitemap.xml')];
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
