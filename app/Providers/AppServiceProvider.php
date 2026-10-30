<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Setting;
use Carbon\Carbon;
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

        // Réseaux sociaux, contact et carte modifiés depuis l'admin (page « Paramètres »)
        Setting::applyToConfig();

        // Catégories affichées dans le menu et le pied de page du site
        View::composer('layouts.site', function ($view) {
            static $menu;
            $menu ??= Category::active()->where('in_menu', true)->get();
            $view->with('navCategories', $menu);
        });
    }
}
