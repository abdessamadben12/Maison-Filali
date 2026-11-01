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
        $this->usePublicFolderOfHosting();
    }

    /**
     * Hébergement mutualisé (LWS…) : souvent, le contenu de « public/ » est copié dans « htdocs / public_html »
     * et le reste du projet est ailleurs. Laravel enregistrait alors les photos dans projet/public/uploads,
     * un dossier que le site ne sert pas → photos invisibles (erreur 404).
     * Ici, on utilise le dossier réellement servi (celui de index.php) pour les photos et images.
     */
    private function usePublicFolderOfHosting(): void
    {
        if ($this->app->runningInConsole()) {
            $custom = env('SHOP_PUBLIC_PATH'); // pour « php artisan » : chemin du dossier htdocs, si besoin
            if ($custom && is_dir($custom)) {
                $this->switchPublicPath(rtrim($custom, '/\\'));
            }

            return;
        }

        $script = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if (! $script || basename($script) !== 'index.php') {
            return;
        }
        $webRoot = dirname($script);
        if ($webRoot !== realpath(public_path())) {
            $this->switchPublicPath($webRoot);
        }
    }

    private function switchPublicPath(string $path): void
    {
        $this->app->usePublicPath($path);
        config(['filesystems.disks.uploads.root' => $path.DIRECTORY_SEPARATOR.'uploads']);
    }

    public function boot(): void
    {
        Carbon::setLocale('fr');

        // Hébergement sans « queue worker » : envoyer les e-mails immédiatement
        if (! config('shop.queue_worker')) {
            config(['queue.default' => 'sync']);
        }

        // En ligne : toutes les URL (canonical, sitemap, partage) selon APP_URL (ex. https://maisonfilali.com)
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
            static $menu, $all;
            // Menu mis en cache (vidé quand une catégorie est modifiée)
            $menu ??= Cache::store('file')->rememberForever('nav.categories.v2', fn () => Category::active()->where('in_menu', true)->take((int) config('shop.menu_max', 4))->get());
            $all ??= Cache::store('file')->rememberForever('footer.categories', fn () => Category::active()->get());
            $view->with('navCategories', $menu)->with('footerCategories', $all);
        });
    }
}
