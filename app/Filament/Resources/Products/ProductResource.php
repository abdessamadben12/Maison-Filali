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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
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
                        ->description('1. Choisissez les couleurs · 2. Choisissez les tailles · 3. Tapez le stock dans le tableau (ou « Même stock partout »).')
                        ->headerActions([self::sizeRangeAction()])
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('stock_colors')->label('Couleurs')->multiple()->options($colorOptions)->allowHtml()
                                    ->searchable()->live()->placeholder('Sans couleur')
                                    ->helperText('Vide = produit sans couleur.'),
                                Select::make('stock_sizes')->label('Tailles')->multiple()->options($sizeOptions)
                                    ->searchable()->live()->placeholder('Taille unique')
                                    ->helperText('Vide = taille unique. « Intervalle de pointures » : 34 → 41 en un clic.'),
                            ]),
                            ViewField::make('stock_matrix')->hiddenLabel()->view('filament.stock-matrix')->default([])
                                ->viewData(fn (Get $get) => [
                                    'matrixColors' => Color::whereIn('id', (array) $get('stock_colors'))->orderBy('position')->get()
                                        ->mapWithKeys(fn ($c) => [$c->id => ['name' => $c->name, 'hex' => $c->hex]])->all(),
                                    'matrixSizes' => Size::whereIn('id', (array) $get('stock_sizes'))->orderBy('position')->get()
                                        ->pluck('name', 'id')->all(),
                                ]),
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

    /**
     * Bouton « Intervalle de tailles » : ex. pointures 34 → 41 → les 8 tailles sont cochées d'un coup
     * (les pointures manquantes sont créées automatiquement). Le stock se tape ensuite dans le tableau.
     */
    private static function sizeRangeAction(): Action
    {
        $clothes = fn () => Size::where('type', 'vetement')->orderBy('position')->pluck('name', 'id')->all();

        return Action::make('sizeRange')
            ->label('Intervalle de pointures / tailles')
            ->icon(Heroicon::OutlinedSquaresPlus)
            ->link()
            ->modalHeading('Choisir un intervalle de tailles')
            ->modalDescription('Toutes les tailles entre le minimum et le maximum sont ajoutées au tableau.')
            ->modalSubmitActionLabel('Ajouter les tailles')
            ->modalWidth('md')
            ->schema([
                ToggleButtons::make('type')->label('Type de tailles')->inline()->required()->live()
                    ->options(['pointure' => 'Pointures (babouches)', 'vetement' => 'Vêtements (S, M, L…)'])
                    ->default('pointure'),
                Grid::make(2)->visible(fn (Get $get) => $get('type') === 'pointure')->schema([
                    TextInput::make('min')->label('Pointure min')->numeric()->integer()->minValue(15)->maxValue(50)->placeholder('34')
                        ->required(fn (Get $get) => $get('type') === 'pointure'),
                    TextInput::make('max')->label('Pointure max')->numeric()->integer()->minValue(15)->maxValue(50)->placeholder('41')
                        ->required(fn (Get $get) => $get('type') === 'pointure'),
                ]),
                Grid::make(2)->visible(fn (Get $get) => $get('type') === 'vetement')->schema([
                    Select::make('from')->label('Taille min')->options($clothes)->required(fn (Get $get) => $get('type') === 'vetement'),
                    Select::make('to')->label('Taille max')->options($clothes)->required(fn (Get $get) => $get('type') === 'vetement'),
                ]),
            ])
            ->action(function (array $data, Get $get, Set $set) {
                $sizes = self::sizesInRange($data);
                if ($sizes->isEmpty()) {
                    Notification::make()->title('Aucune taille dans cet intervalle')->warning()->send();

                    return;
                }
                $ids = collect((array) $get('stock_sizes'))->map(fn ($id) => (string) $id)
                    ->merge($sizes->pluck('id')->map(fn ($id) => (string) $id))->unique()->values()->all();
                $set('stock_sizes', $ids);
                Notification::make()->title($sizes->count().' taille(s) ajoutée(s) au tableau')
                    ->body('Tapez maintenant le stock, puis « Enregistrer ».')->success()->send();
            });
    }

    /**
     * Enregistre le tableau couleurs × tailles dans les variantes du produit.
     * Les changements de stock sont gardés dans l'historique (menu Stocks).
     *
     * @param  array<int, int|string>  $colors
     * @param  array<int, int|string>  $sizes
     * @param  array<string, int|string>  $matrix  « c{couleur}s{taille} » => stock
     */
    public static function syncVariants(Product $product, array $colors, array $sizes, array $matrix): void
    {
        $colors = array_values(array_unique(array_map('intval', array_filter($colors)))) ?: [0];
        $sizes = array_values(array_unique(array_map('intval', array_filter($sizes)))) ?: [0];
        $existing = $product->variants()->get();
        $keep = [];

        foreach ($colors as $c) {
            foreach ($sizes as $s) {
                $stock = max(0, (int) ($matrix['c'.$c.'s'.$s] ?? 0));
                $variant = $existing->first(fn ($v) => (int) $v->color_id === $c && (int) $v->size_id === $s);
                if (! $variant) {
                    $variant = $product->variants()->create(['color_id' => $c ?: null, 'size_id' => $s ?: null, 'stock' => 0]);
                    if ($stock > 0) {
                        $variant->adjustStock($stock, 'reception_atelier', 'Ajouté depuis la fiche produit');
                    }
                } elseif ($variant->stock !== $stock) {
                    $variant->adjustStock($stock - $variant->stock, 'correction', 'Modifié depuis la fiche produit');
                }
                $keep[] = $variant->id;
            }
        }

        // Couleurs / tailles retirées du tableau
        $product->variants()->whereNotIn('id', $keep)->delete();
    }

    /** Remplit le tableau à partir des variantes existantes (page « Modifier »). */
    public static function variantsToForm(Product $product): array
    {
        $variants = $product->variants()->get();

        return [
            'stock_colors' => $variants->pluck('color_id')->filter()->unique()->map(fn ($id) => (string) $id)->values()->all(),
            'stock_sizes' => $variants->pluck('size_id')->filter()->unique()->map(fn ($id) => (string) $id)->values()->all(),
            'stock_matrix' => $variants->mapWithKeys(fn ($v) => ['c'.((int) $v->color_id).'s'.((int) $v->size_id) => (int) $v->stock])->all(),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Size> */
    private static function sizesInRange(array $data)
    {
        if (($data['type'] ?? null) === 'vetement') {
            $from = Size::find($data['from'] ?? 0);
            $to = Size::find($data['to'] ?? 0);
            if (! $from || ! $to) {
                return collect();
            }
            [$a, $b] = [min($from->position, $to->position), max($from->position, $to->position)];

            return Size::where('type', 'vetement')->whereBetween('position', [$a, $b])->orderBy('position')->get();
        }

        // Pointures : min et max dans n'importe quel ordre (37 → 34 = 34 → 37)
        [$a, $b] = [min((int) $data['min'], (int) $data['max']), max((int) $data['min'], (int) $data['max'])];
        $existing = Size::where('type', 'pointure')->get()->keyBy(fn ($s) => trim($s->name));
        $created = false;
        for ($n = $a; $n <= $b; $n++) {
            if (! $existing->has((string) $n)) {
                $existing->put((string) $n, Size::create(['name' => (string) $n, 'type' => 'pointure', 'position' => 0]));
                $created = true;
            }
        }
        if ($created) {
            // Ranger toutes les pointures dans l'ordre numérique (après les tailles vêtement)
            $start = (int) Size::where('type', 'vetement')->max('position') + 1;
            Size::where('type', 'pointure')->get()
                ->sortBy(fn ($s) => is_numeric($s->name) ? (float) $s->name : 999)
                ->values()->each(fn ($s, $i) => $s->position === $start + $i ?: $s->update(['position' => $start + $i]));
        }

        return collect(range($a, $b))->map(fn ($n) => $existing->get((string) $n))->filter()->values();
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
