<?php

namespace App\Filament\Resources\Packs;

use App\Filament\Resources\Packs\Pages\CreatePack;
use App\Filament\Resources\Packs\Pages\EditPack;
use App\Filament\Resources\Packs\Pages\ListPacks;
use App\Models\Pack;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Admin → Offres / packs : le gérant compose des packs (plusieurs produits à un prix réduit)
 * affichés sur la page « Offres » du site.
 */
class PackResource extends Resource
{
    protected static ?string $model = Pack::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Offres / packs';

    protected static ?string $modelLabel = 'offre';

    protected static ?string $pluralModelLabel = 'offres';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'offres';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isGerant();
    }

    /** Valeur des produits choisis dans le formulaire (prix normal). */
    private static function itemsValue(Get $get): int
    {
        $items = collect($get('items') ?? []);
        $prices = Product::whereIn('id', $items->pluck('product_id')->filter())->pluck('price', 'id');

        return (int) $items->sum(fn ($i) => ($prices[$i['product_id'] ?? 0] ?? 0) * max(1, (int) ($i['quantity'] ?? 1)));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Group::make()->columnSpan(['lg' => 2])->schema([
                    Section::make('Offre')->columns(2)->schema([
                        TextInput::make('name')->label('Nom de l’offre')->required()->maxLength(120)->columnSpanFull()
                            ->placeholder('Ex. : Pack Mariée — caftan + babouches'),
                        TextInput::make('tagline')->label('Accroche')->maxLength(190)->columnSpanFull()
                            ->placeholder('Ex. : L’ensemble complet pour vos fêtes, à prix doux'),
                        Textarea::make('description')->label('Description')->rows(4)->columnSpanFull(),
                    ]),
                    Section::make('Produits du pack')
                        ->description('Ajoutez les produits du pack. Le client choisira la couleur et la taille de chacun en commandant.')
                        ->schema([
                            Repeater::make('items')->hiddenLabel()->relationship()->orderColumn('position')
                                ->columns(['default' => 4])->defaultItems(2)->minItems(1)->live()
                                ->addActionLabel('Ajouter un produit')
                                ->schema([
                                    Select::make('product_id')->label('Produit')->required()->searchable()->preload()->columnSpan(3)
                                        ->options(fn () => Product::with('category')->orderBy('name')->get()
                                            ->mapWithKeys(fn ($p) => [$p->id => $p->name.' — '.money($p->price).($p->is_active ? '' : ' (hors ligne)')])->all())
                                        ->live()->distinct(),
                                    TextInput::make('quantity')->label('Qté')->numeric()->integer()->minValue(1)->maxValue(10)->default(1)->required()->live(onBlur: true),
                                ]),
                        ]),
                    Section::make('Photos')->schema([
                        FileUpload::make('images')->hiddenLabel()->image()->multiple()->reorderable()->appendFiles()
                            ->disk('uploads')->directory('packs')->visibility('public')
                            ->maxFiles(6)->maxSize(10240)->imageEditor()->panelLayout('grid')
                            ->helperText('Facultatif : sans photo, celles des produits du pack sont utilisées. Converties en WebP automatiquement.'),
                    ]),
                ]),
                Group::make()->columnSpan(1)->schema([
                    Section::make('Prix')->schema([
                        Placeholder::make('value')->label('Valeur des produits (prix normal)')
                            ->content(fn (Get $get) => new HtmlString('<b style="font-size:18px">'.money(self::itemsValue($get)).'</b>')),
                        TextInput::make('price')->label('Prix du pack')->numeric()->integer()->minValue(0)->required()->suffix('DH')->live(onBlur: true),
                        Placeholder::make('saving')->label('Réduction pour le client')
                            ->content(function (Get $get) {
                                $value = self::itemsValue($get);
                                $price = (int) $get('price');
                                if (! $value || ! $price) {
                                    return '—';
                                }
                                if ($price >= $value) {
                                    return new HtmlString('<span style="color:#b45309">Le prix du pack n’est pas inférieur à la valeur des produits.</span>');
                                }

                                return new HtmlString('<b style="color:#15803d">−'.round(($value - $price) * 100 / $value).' %</b> · '.money($value - $price).' d’économie');
                            }),
                        TextInput::make('compare_price')->label('Prix barré (facultatif)')->numeric()->integer()->minValue(0)->suffix('DH')
                            ->helperText('Vide = la valeur des produits.'),
                    ]),
                    Section::make('Affichage')->schema([
                        Toggle::make('is_active')->label('En ligne')->default(true),
                        Toggle::make('is_featured')->label('Afficher sur l’accueil')->default(true),
                        DateTimePicker::make('starts_at')->label('Début (facultatif)')->native(false)->displayFormat('d/m/Y H:i')->seconds(false),
                        DateTimePicker::make('ends_at')->label('Fin (facultatif)')->native(false)->displayFormat('d/m/Y H:i')->seconds(false)
                            ->after('starts_at')->helperText('Après cette date, l’offre disparaît du site.'),
                        TextInput::make('position')->label('Ordre d’affichage')->numeric()->default(0),
                    ]),
                    Section::make('Lien')->schema([
                        TextInput::make('slug')->label('Adresse de la page')->prefix('/offres/')
                            ->helperText('Laissez vide : rempli automatiquement depuis le nom.')
                            ->unique(ignoreRecord: true),
                    ])->collapsed(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('items.product.variants'))
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                ImageColumn::make('images')->label('')->state(fn (Pack $r) => $r->thumb_urls[0])->imageWidth(64)->imageHeight(48),
                TextColumn::make('name')->label('Offre')->searchable()->weight('medium')
                    ->description(fn (Pack $r) => $r->items->map(fn ($i) => ($i->quantity > 1 ? $i->quantity.'× ' : '').$i->product?->name)->filter()->implode(' + ')),
                TextColumn::make('price')->label('Prix')->formatStateUsing(fn ($state) => money($state))->color('primary')->weight('medium')
                    ->description(fn (Pack $r) => $r->savings > 0 ? 'au lieu de '.money($r->compare_total).' (−'.$r->savings_percent.' %)' : null),
                TextColumn::make('status')->label('État')->badge()
                    ->state(fn (Pack $r) => match (true) {
                        ! $r->is_active => 'Hors ligne',
                        $r->starts_at && $r->starts_at->isFuture() => 'À venir',
                        $r->ends_at && $r->ends_at->isPast() => 'Terminée',
                        ! $r->inStock() => 'Stock insuffisant',
                        default => 'En ligne',
                    })
                    ->color(fn ($state) => match ($state) { 'En ligne' => 'success', 'À venir' => 'info', 'Stock insuffisant' => 'warning', default => 'gray' })
                    ->description(fn (Pack $r) => $r->ends_at ? 'jusqu’au '.$r->ends_at->format('d/m/Y') : null),
                ToggleColumn::make('is_active')->label('En ligne'),
            ])
            ->recordActions([
                Action::make('site')->label('')->tooltip('Voir sur le site')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (Pack $r) => route('packs.show', $r), true)->visible(fn (Pack $r) => $r->isAvailable()),
                EditAction::make()->label('')->tooltip('Modifier'),
                DeleteAction::make()->label('')->tooltip('Supprimer')
                    ->modalDescription('L’offre disparaît du site. Les commandes déjà passées restent visibles.'),
            ])
            ->emptyStateHeading('Aucune offre')
            ->emptyStateDescription('Créez un pack : plusieurs produits vendus ensemble à un prix réduit.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPacks::route('/'),
            'create' => CreatePack::route('/creer'),
            'edit' => EditPack::route('/{record}/modifier'),
        ];
    }
}
