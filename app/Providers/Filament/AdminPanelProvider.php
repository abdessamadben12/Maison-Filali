<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            // « Mot de passe oublié ? » : lien de réinitialisation envoyé par e-mail
            ->passwordReset()
            ->brandName('Maison Filali')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('44px')
            ->favicon(asset('images/favicon-48.png'))
            ->font('Jost')
            ->colors([
                // Vert émeraude Maison Filali (#0f4a3e = nuance 600 utilisée par les boutons)
                'primary' => collect([
                    50 => '#eef5f2', 100 => '#d5e8e0', 200 => '#aed2c4', 300 => '#7fb5a2', 400 => '#4f9580',
                    500 => '#2b7563', 600 => '#0f4a3e', 700 => '#0c3d33', 800 => '#0a322a', 900 => '#082821', 950 => '#041612',
                ])->map(fn ($hex) => Color::convertToOklch($hex))->all(),
                'gray' => Color::Stone,
                'warning' => Color::Amber,
                'success' => Color::Emerald,
                'info' => Color::Blue,
                'danger' => Color::Red,
            ])
            ->sidebarWidth('16rem')
            ->maxContentWidth('full')
            ->databaseNotifications(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.admin-style'))
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn () => Blade::render(
                '<aside class="mf-login-aside"><img class="pattern" src="{{ asset(\'images/zellige-tile.png\') }}" alt=""><div class="frame"><img src="{{ asset(\'images/login-photo.webp\') }}" alt=""></div><blockquote>« L’atelier, côté coulisses. »</blockquote></aside>'
            ))
            // Nouvelles commandes en direct (notification + son), sans recharger la page
            ->renderHook(PanelsRenderHook::BODY_END, fn () => auth()->check() ? Blade::render('@livewire(\App\Livewire\NewOrdersWatcher::class)') : '')
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => Blade::render(
                '<a href="{{ url(\'/\') }}" target="_blank" class="mf-view-site">Voir le site ↗</a>'
            ))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
