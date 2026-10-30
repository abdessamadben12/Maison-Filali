<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('fr');

        // En ligne : toutes les URL (canonical, sitemap, partage) en https://maisonfilali.ma
        if ($this->app->isProduction()) {
            URL::forceRootUrl(config('app.url'));
            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        // Développement : signaler dans storage/logs les requêtes SQL en boucle (N+1)
        if (! $this->app->isProduction()) {
            Model::preventLazyLoading();
            Model::handleLazyLoadingViolationUsing(fn ($model, $relation) => logger()->warning('N+1 : '.$model::class.'::'.$relation.' chargé en boucle'));
        }

        // Réseaux sociaux, contact et carte modifiés depuis l'admin (page « Paramètres »)
        Setting::applyToConfig();

        // Catégories affichées dans le menu et le pied de page du site
        View::composer('layouts.site', function ($view) {
            static $menu;
            // Menu mis en cache (vidé quand une catégorie est modifiée)
            $menu ??= Cache::rememberForever('nav.categories', fn () => Category::active()->where('in_menu', true)->get());
            $view->with('navCategories', $menu);
        });
    }
}
