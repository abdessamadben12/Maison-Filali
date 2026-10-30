<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Produits';

    protected static ?string $modelLabel = 'produit';

    protected static ?string $pluralModelLabel = 'produits';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'produits';

    protected static ?int $navigationSort = 2;

    /** Réservé au gérant (la société de confirmation n'y a pas accès). */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isGerant();
    }

    public static function form(Schema $schema): Schema
    {
        $colorOptions = fn () => Color::orderBy('position')->get()
            ->mapWithKeys(fn ($c) => [$c->id => '<span class="mf-swatch" style="background:'.e($c->hex).'"></span>'.e($c->name)])->all();
        $sizeOptions = fn () => Size::orderBy('position')->get()->groupBy(fn ($s) => Size::TYPES[$s->type])
            ->map(fn ($g) => $g->pluck('name', 'id')->all())->all();

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Group::make()->columnSpan(['lg' => 2])->schema([
                    Section::make('Informations')->columns(2)->schema([
                        TextInput::make('name')->label('Nom du produit')->required()->maxLength(120),
                        Select::make('category_id')->label('Catégorie')->relationship('category', 'name')->required()->preload()
                            ->createOptionForm([TextInput::make('name')->label('Nom')->required()]),
                        Textarea::make('tagline')->label('Accroche (sous le prix)')->rows(2)->columnSpanFull(),
                        Textarea::make('description')->label('Description')->rows(4)->columnSpanFull(),
                        Textarea::make('materials')->label('Matières & entretien')->rows(3)->columnSpanFull(),
                        TextInput::make('price')->label('Prix (DH)')->numeric()->minValue(0)->required()->suffix('DH'),
                        TextInput::make('compare_price')->label('Prix barré (DH)')->numeric()->minValue(0)->suffix('DH')
                            ->helperText('Facultatif : ancien prix affiché barré.'),
                    ]),
                    Section::make('Photos')->schema([
                        FileUpload::make('images')->hiddenLabel()->image()->multiple()->reorderable()->appendFiles()
                            ->disk('uploads')->directory('products')->visibility('public')
                            ->maxFiles(8)->maxSize(10240)->imageEditor()->panelLayout('grid')
                            ->helperText('La première photo est la photo principale. Glissez pour changer l’ordre. JPG, PNG ou WebP : les photos sont converties automatiquement en WebP, sans perte de qualité.'),
                    ]),
                    Section::make('Couleurs, tailles & stock')
                        ->description('Une ligne par combinaison couleur / taille. Laissez la couleur ou la taille vide si le produit n’en a pas.')
                        ->schema([
                            Repeater::make('variants')->hiddenLabel()->relationship()->columns(3)
                                ->schema([
                                    Select::make('color_id')->label('Couleur')->options($colorOptions)->allowHtml()->searchable(false)->placeholder('—'),
                                    Select::make('size_id')->label('Taille')->options($sizeOptions)->placeholder('—'),
                                    TextInput::make('stock')->label('Stock')->numeric()->default(0)->required()->minValue(0),
                                ])
                                ->addActionLabel('Ajouter une ligne')
                                ->defaultItems(1)->reorderable(false)->collapsible(false)
                                ->itemLabel(null),
                        ]),
                ]),
                Group::make()->columnSpan(1)->schema([
                    Section::make('Affichage')->schema([
                        Toggle::make('is_active')->label('En ligne')->default(true),
                        Toggle::make('is_new')->label('Nouveauté (accueil)'),
                        Toggle::make('is_featured')->label('Mis en avant sur l’accueil'),
                        Toggle::make('is_custom_possible')->label('Sur mesure possible'),
                        TextInput::make('position')->label('Ordre d’affichage')->numeric()->default(0),
                    ]),
                    Section::make('Lien')->schema([
                        TextInput::make('slug')->label('Adresse de la page')->prefix('/produit/')
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'variants.color', 'variants.size']))
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                ImageColumn::make('images')->label('')->state(fn (Product $r) => $r->image_url)->imageWidth(44)->imageHeight(56),
                TextColumn::make('name')->label('Produit')->searchable()->weight('medium')
                    ->description(function (Product $r) {
                        $c = $r->availableColors()->count();
                        $s = $r->availableSizes();

                        return collect([$c ? $c.' couleur'.($c > 1 ? 's' : '') : null, $s->count() ? $s->count().' '.($s->first()->type === 'pointure' ? 'pointures' : 'tailles') : null])->filter()->implode(' · ');
                    }),
                TextColumn::make('category.name')->label('Catégorie')->sortable(),
                TextColumn::make('price')->label('Prix')->sortable()->formatStateUsing(fn ($state) => money($state))->color('primary')->weight('medium'),
                TextColumn::make('total_stock')->label('Stock')->badge()
                    ->state(fn (Product $r) => $r->total_stock)
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state.' en stock' : 'Épuisé')
                    ->color(fn ($state) => $state <= 0 ? 'danger' : ($state <= config('shop.low_stock') ? 'warning' : 'success')),
                ToggleColumn::make('is_active')->label('En ligne'),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Catégorie')->relationship('category', 'name'),
            ])
            ->recordActions([
                Action::make('site')->label('')->tooltip('Voir sur le site')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (Product $r) => route('product', $r), true),
                EditAction::make()->label('')->tooltip('Modifier'),
                DeleteAction::make()->label('')->tooltip('Supprimer')
                    ->modalDescription(new HtmlString('Le produit disparaît du site. Les anciennes commandes restent visibles.<br>Astuce : vous pouvez aussi simplement le mettre « hors ligne ».')),
            ])
            ->emptyStateHeading('Aucun produit');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
