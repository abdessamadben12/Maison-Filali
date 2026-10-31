<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\WhatsAppAlert;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
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

    /** Réservé au gérant (la société de confirmation n'y a pas accès). */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isGerant();
    }

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
            'contact_email' => config('shop.contact_email'),
            'whatsapp' => config('shop.whatsapp'),
            'hours' => config('shop.hours'),
            'address' => config('shop.address'),
            'instagram' => config('shop.instagram_url'),
            'facebook_url' => config('shop.facebook_url'),
            'tiktok_url' => config('shop.tiktok_url'),
            'maps_embed' => config('shop.maps_embed'),
            'maps_url' => config('shop.maps_url'),
            'order_alerts' => self::allAlerts(),
            'wa_phone_id' => config('shop.wa_phone_id'),
            'wa_token' => config('shop.wa_token'),
            'wa_template' => config('shop.wa_template'),
            'wa_lang' => config('shop.wa_lang'),
            'wa_client_template' => config('shop.wa_client_template'),
            'google_verification' => config('shop.google_verification'),
            'bing_verification' => config('shop.bing_verification'),
            'shipping_fee' => config('shop.shipping_fee'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Contact')->icon(Heroicon::OutlinedPhone)->columns(2)->schema([
                TextInput::make('phone')->label('Téléphone affiché')->required()->maxLength(30)->placeholder('06 78 20 88 68'),
                TextInput::make('contact_email')->label('E-mail qui reçoit les messages du formulaire')->email()->maxLength(190)
                    ->placeholder('contact@maisonfilali.com')
                    ->helperText('Chaque message envoyé depuis la page Contact arrive à cette adresse (et reste visible dans « Messages »).'),
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

            Section::make('Référencement (SEO)')->icon(Heroicon::OutlinedMagnifyingGlass)->columns(3)
                ->description('Codes de vérification des moteurs de recherche et informations lues par Google.')
                ->schema([
                    TextInput::make('google_verification')->label('Google Search Console')->maxLength(300)
                        ->placeholder('<meta name="google-site-verification" content="…">')
                        ->helperText('Search Console → Ajouter une propriété → « Balise HTML » : collez la balise ou seulement le code.'),
                    TextInput::make('bing_verification')->label('Bing Webmaster Tools')->maxLength(300)
                        ->placeholder('<meta name="msvalidate.01" content="…">')
                        ->helperText('Facultatif : Bing peut aussi importer le site depuis Search Console.'),
                    TextInput::make('shipping_fee')->label('Frais de livraison au Maroc (DH)')->numeric()->minValue(0)->maxValue(1000)->placeholder('35')
                        ->helperText('0 = livraison offerte. Affiché dans « Livraison & retours » et sur Google (fiches produits).'),
                ]),

            Section::make('WhatsApp (Meta for Developers)')->icon(Heroicon::OutlinedBellAlert)->collapsible()
                ->description('À chaque nouvelle commande, l’API officielle WhatsApp Cloud envoie une alerte à l’équipe et, si vous le souhaitez, une confirmation au client.')
                ->schema([
                    Placeholder::make('wa_help')->hiddenLabel()->content(new HtmlString(
                        '<div style="font-size:13px;line-height:1.65">'
                        .'<b>1. Identifiants</b> — sur <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener" style="text-decoration:underline">developers.facebook.com</a> → votre application → <b>WhatsApp → Configuration de l’API</b> : copiez « Identifiant du numéro de téléphone ». '
                        .'Pour le jeton, utilisez un <b>jeton permanent</b> (Business Settings → Utilisateurs système → Générer un jeton, autorisations <i>whatsapp_business_messaging</i> et <i>whatsapp_business_management</i>) : le jeton temporaire expire après 24 h.<br>'
                        .'<b>2. Modèles</b> — dans <b>WhatsApp Manager → Modèles de message</b>, créez des modèles catégorie <b>Utilité</b>, langue <b>Français</b> :<br>'
                        .'• <code>maison_filali_commande</code> (alerte équipe) : <span style="white-space:pre-line;display:block;margin:4px 0 6px 12px;padding:6px 10px;background:rgba(0,0,0,.04);border-radius:6px">'.e(WhatsAppAlert::TEAM_TEMPLATE_TEXT).'</span>'
                        .'• <code>confirmation_commande</code> (client, facultatif) : <span style="display:block;margin:4px 0 6px 12px;padding:6px 10px;background:rgba(0,0,0,.04);border-radius:6px">'.e(WhatsAppAlert::CLIENT_TEMPLATE_TEXT).'</span>'
                        .'<b>3. Tester</b> — en attendant l’approbation, mettez <code>hello_world</code> comme modèle et cliquez sur ✈️ à côté d’un numéro. En mode test, chaque numéro doit d’abord être ajouté dans la liste « À » de Meta.'
                        .'</div>')),
                    Grid::make(2)->schema([
                        TextInput::make('wa_phone_id')->label('Identifiant du numéro (Phone number ID)')->maxLength(40)->placeholder('123456789012345')
                            ->rule('nullable')->regex('/^\d{5,25}$/')->validationMessages(['regex' => 'L’identifiant ne contient que des chiffres (ce n’est pas le numéro de téléphone).']),
                        TextInput::make('wa_token')->label('Jeton d’accès (Access token)')->password()->revealable()->maxLength(600)->placeholder('EAAG…'),
                        TextInput::make('wa_template')->label('Modèle « alerte équipe »')->maxLength(100)->placeholder('maison_filali_commande')
                            ->helperText('6 variables : n° · articles · total · client · téléphone · ville.'),
                        TextInput::make('wa_lang')->label('Langue des modèles')->maxLength(10)->placeholder('fr')
                            ->helperText('Code de langue choisi dans Meta : fr, ar, en_US…'),
                        TextInput::make('wa_client_template')->label('Modèle « confirmation client » (facultatif)')->maxLength(100)->placeholder('confirmation_commande')
                            ->helperText('Laissez vide pour ne rien envoyer au client. 3 variables : prénom · n° · total.'),
                    ]),
                    Repeater::make('order_alerts')->label('Numéros de l’équipe qui reçoivent l’alerte')->defaultItems(0)->maxItems(10)->columns(3)
                        ->addActionLabel('Ajouter un numéro')
                        ->itemLabel(fn (array $state) => trim(($state['name'] ?? '') ?: ($state['phone'] ?? '')) ?: 'Nouveau numéro')
                        ->extraItemActions([
                            Action::make('testAlert')->icon(Heroicon::OutlinedPaperAirplane)->tooltip('Envoyer un message de test')
                                ->action(function (array $arguments, Repeater $component) {
                                    $item = $component->getRawItemState($arguments['item']);
                                    if (blank($item['phone'] ?? null)) {
                                        Notification::make()->title('Indiquez le numéro')->warning()->send();

                                        return;
                                    }
                                    // Utilise les valeurs du formulaire, même pas encore enregistrées
                                    config([
                                        'shop.wa_phone_id' => trim((string) ($this->data['wa_phone_id'] ?? '')),
                                        'shop.wa_token' => trim((string) ($this->data['wa_token'] ?? '')),
                                        'shop.wa_lang' => trim((string) ($this->data['wa_lang'] ?? '')),
                                    ]);
                                    $template = trim((string) ($this->data['wa_template'] ?? '')) ?: 'maison_filali_commande';
                                    try {
                                        $error = WhatsAppAlert::sendTemplate((string) $item['phone'], $template, WhatsAppAlert::sampleTeamParams());
                                    } catch (\Throwable $e) {
                                        $error = 'Erreur inattendue : '.$e->getMessage();
                                    }
                                    $error
                                        ? Notification::make()->title('Message non envoyé')->body($error)->danger()->persistent()->send()
                                        : Notification::make()->title('Message accepté par Meta')->body('Modèle « '.$template.' » envoyé à '.$item['phone'].'. Vérifiez WhatsApp.')->success()->send();
                                }),
                        ])
                        ->schema([
                            TextInput::make('name')->label('Nom')->maxLength(40)->placeholder('Gérant'),
                            TextInput::make('phone')->label('Numéro WhatsApp')->required()->tel()->maxLength(20)->placeholder('06 78 20 88 68 ou +33 6…'),
                            Toggle::make('active')->label('Actif')->default(true)->inline(false),
                        ]),
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
            'contact_email' => trim((string) ($d['contact_email'] ?? '')) ?: null,
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
            'order_alerts' => ($alerts = collect($d['order_alerts'] ?? [])->map(fn ($r) => [
                'name' => trim((string) ($r['name'] ?? '')) ?: null,
                'phone' => trim((string) $r['phone']),
                'active' => (bool) ($r['active'] ?? true),
            ])->values()->all()) ? json_encode($alerts, JSON_UNESCAPED_UNICODE) : null,
            'wa_phone_id' => trim((string) ($d['wa_phone_id'] ?? '')) ?: null,
            'wa_token' => trim((string) ($d['wa_token'] ?? '')) ?: null,
            'wa_template' => trim((string) ($d['wa_template'] ?? '')) ?: null,
            'wa_lang' => trim((string) ($d['wa_lang'] ?? '')) ?: null,
            'wa_client_template' => trim((string) ($d['wa_client_template'] ?? '')) ?: null,
            'google_verification' => trim((string) ($d['google_verification'] ?? '')) ?: null,
            'bing_verification' => trim((string) ($d['bing_verification'] ?? '')) ?: null,
            'shipping_fee' => ($d['shipping_fee'] ?? '') === '' || $d['shipping_fee'] === null ? null : (string) (0 + $d['shipping_fee']),
        ]);

        Setting::applyToConfig();
        $this->mount();

        Notification::make()->title('Paramètres enregistrés')->body('Le site est à jour.')->success()->send();
    }

    /** Tous les destinataires enregistrés (y compris ceux mis en pause). */
    private static function allAlerts(): array
    {
        $list = config('shop.order_alerts');
        $list = is_string($list) ? json_decode($list, true) : $list;

        return is_array($list) ? array_values($list) : [];
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
