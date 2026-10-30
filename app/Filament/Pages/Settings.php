<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Admin → Paramètres : réseaux sociaux, WhatsApp, coordonnées et carte Google Maps du site.
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Paramètres';

    protected static ?string $title = 'Paramètres du site';

    protected static ?string $slug = 'parametres';

    protected static ?int $navigationSort = 20;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return 'Coordonnées, réseaux sociaux et carte affichés sur le site. Les changements sont visibles immédiatement.';
    }

    public function mount(): void
    {
        $this->form->fill([
            'phone' => config('shop.phone'),
            'whatsapp' => config('shop.whatsapp'),
            'hours' => config('shop.hours'),
            'address' => config('shop.address'),
            'instagram' => config('shop.instagram_url'),
            'facebook_url' => config('shop.facebook_url'),
            'tiktok_url' => config('shop.tiktok_url'),
            'maps_embed' => config('shop.maps_embed'),
            'maps_url' => config('shop.maps_url'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Contact')->icon(Heroicon::OutlinedPhone)->columns(2)->schema([
                TextInput::make('phone')->label('Téléphone affiché')->required()->maxLength(30)->placeholder('06 78 20 88 68'),
                TextInput::make('whatsapp')->label('Numéro WhatsApp')->required()->maxLength(30)->placeholder('06 78 20 88 68')
                    ->helperText('Format 06… ou 2126… : utilisé pour tous les boutons « WhatsApp » du site.'),
                TextInput::make('hours')->label('Horaires')->required()->maxLength(80)->placeholder('Lundi – Samedi · 10h – 20h'),
                Textarea::make('address')->label('Adresse')->required()->rows(2)->maxLength(255),
            ]),

            Section::make('Réseaux sociaux')->icon(Heroicon::OutlinedHeart)
                ->description('Laissez un champ vide pour masquer l’icône sur le site.')->columns(3)->schema([
                    TextInput::make('instagram')->label('Instagram')->maxLength(255)
                        ->placeholder('@la_maison_filalii ou https://instagram.com/…')
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if ($value && ! self::instagramHandle($value)) {
                                $fail('Indiquez @nom_du_compte ou le lien du profil Instagram.');
                            }
                        }),
                    TextInput::make('facebook_url')->label('Facebook')->url()->maxLength(255)->placeholder('https://www.facebook.com/…'),
                    TextInput::make('tiktok_url')->label('TikTok')->url()->maxLength(255)->placeholder('https://www.tiktok.com/@…'),
                ]),

            Section::make('Localisation Google Maps')->icon(Heroicon::OutlinedMapPin)->schema([
                Textarea::make('maps_embed')->label('Carte intégrée')->rows(4)->live(onBlur: true)
                    ->helperText(new HtmlString('Sur Google Maps : ouvrez la fiche « Maison Filali » → <b>Partager</b> → <b>Intégrer une carte</b> → <b>Copier le HTML</b>, puis collez ici. Vide = image du plan.'))
                    ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                        if (filled($value) && ! Setting::mapsEmbedSrc($value)) {
                            $fail('Collez le code « Intégrer une carte » de Google Maps (il commence par <iframe src="https://www.google.com/maps/embed…).');
                        }
                    }),
                Placeholder::make('preview')->label('Aperçu')
                    ->visible(fn (Get $get) => (bool) Setting::mapsEmbedSrc($get('maps_embed')))
                    ->content(fn (Get $get) => new HtmlString('<iframe src="'.e(Setting::mapsEmbedSrc($get('maps_embed'))).'" style="width:100%;height:320px;border:0;border-radius:8px" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>')),
                TextInput::make('maps_url')->label('Lien du bouton « Itinéraire »')->url()->maxLength(500)
                    ->helperText('Laissez vide : il est calculé automatiquement à partir de la carte.'),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Enregistrer')->submit('save')->keyBindings(['mod+s']),
                    ])->sticky(),
                ]),
        ]);
    }

    public function save(): void
    {
        $d = $this->form->getState();

        $handle = self::instagramHandle($d['instagram'] ?? null);
        $embed = Setting::mapsEmbedSrc($d['maps_embed'] ?? null);
        $whatsapp = preg_replace('/\D+/', '', (string) $d['whatsapp']);
        if (str_starts_with($whatsapp, '00')) {
            $whatsapp = substr($whatsapp, 2);
        } elseif (str_starts_with($whatsapp, '0')) {
            $whatsapp = '212'.substr($whatsapp, 1);
        }

        Setting::saveMany([
            'phone' => trim($d['phone']),
            'whatsapp' => $whatsapp,
            'hours' => trim($d['hours']),
            'hours_short' => trim($d['hours']),
            'address' => trim($d['address']),
            'address_short' => trim($d['address']),
            'instagram' => $handle ? '@'.$handle : null,
            'instagram_url' => $handle ? 'https://www.instagram.com/'.$handle.'/' : null,
            'facebook_url' => $d['facebook_url'] ?: null,
            'tiktok_url' => $d['tiktok_url'] ?: null,
            'maps_embed' => $embed,
            'maps_url' => $d['maps_url'] ?: (Setting::mapsLinkFromEmbed($embed) ?? config('shop.maps_url')),
        ]);

        Setting::applyToConfig();
        $this->mount();

        Notification::make()->title('Paramètres enregistrés')->body('Le site est à jour.')->success()->send();
    }

    /** « @compte », « compte » ou un lien instagram.com/compte → « compte ». */
    public static function instagramHandle(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('#instagram\.com/([A-Za-z0-9._]+)#i', $value, $m)) {
            $value = $m[1];
        }
        $value = ltrim($value, '@');

        return preg_match('/^[A-Za-z0-9._]{1,30}$/', $value) && ! in_array(strtolower($value), ['p', 'reel', 'reels', 'stories', 'explore'], true) ? $value : null;
    }
}
