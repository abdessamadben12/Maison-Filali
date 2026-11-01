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
                        ->description('Une ligne par combinaison couleur / taille. Laissez la couleur ou la taille vide si le produit n’en a pas. Astuce : « Couleur + intervalle de tailles » crée toutes les tailles d’une couleur en une fois.')
                        ->headerActions([self::sizeRangeAction($colorOptions)])
                        ->schema([
                            Repeater::make('variants')->hiddenLabel()->relationship()->columns(3)
                                // Deux lignes identiques (même couleur + même taille) sont refusées
                                ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                                    $seen = [];
                                    foreach ((array) $value as $row) {
                                        $key = ($row['color_id'] ?? '').'-'.($row['size_id'] ?? '');
                                        if (isset($seen[$key])) {
                                            $color = Color::find($row['color_id'] ?? 0)?->name ?? 'sans couleur';
                                            $size = Size::find($row['size_id'] ?? 0)?->name ?? 'sans taille';
                                            $fail("La combinaison {$color} / {$size} apparaît deux fois : gardez une seule ligne.");

                                            return;
                                        }
                                        $seen[$key] = true;
                                    }
                                }])
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

    /**
     * Bouton « Couleur + intervalle de tailles » : ex. babouches Rose de la pointure 34 à 37
     * → 4 lignes (34, 35, 36, 37) créées d'un coup. Les pointures manquantes sont ajoutées automatiquement.
     */
    private static function sizeRangeAction(\Closure $colorOptions): Action
    {
        $clothes = fn () => Size::where('type', 'vetement')->orderBy('position')->pluck('name', 'id')->all();

        return Action::make('sizeRange')
            ->label('Couleur + intervalle de tailles')
            ->icon(Heroicon::OutlinedSquaresPlus)
            ->link()
            ->modalHeading('Ajouter une couleur avec un intervalle de tailles')
            ->modalDescription('Toutes les tailles entre le minimum et le maximum sont ajoutées, avec le même stock pour chacune.')
            ->modalSubmitActionLabel('Ajouter les tailles')
            ->modalWidth('lg')
            ->schema([
                Select::make('color_id')->label('Couleur')->options($colorOptions)->allowHtml()->searchable(false)
                    ->placeholder('Sans couleur'),
                ToggleButtons::make('type')->label('Type de tailles')->inline()->required()->live()
                    ->options(['pointure' => 'Pointures (babouches)', 'vetement' => 'Tailles vêtement (S, M, L…)'])
                    ->default('pointure'),
                Grid::make(2)->visible(fn (Get $get) => $get('type') === 'pointure')->schema([
                    TextInput::make('min')->label('Pointure min')->numeric()->integer()->minValue(15)->maxValue(50)->placeholder('34')
                        ->required(fn (Get $get) => $get('type') === 'pointure'),
                    TextInput::make('max')->label('Pointure max')->numeric()->integer()->minValue(15)->maxValue(50)->placeholder('37')
                        ->required(fn (Get $get) => $get('type') === 'pointure'),
                ]),
                Grid::make(2)->visible(fn (Get $get) => $get('type') === 'vetement')->schema([
                    Select::make('from')->label('Taille min')->options($clothes)->required(fn (Get $get) => $get('type') === 'vetement'),
                    Select::make('to')->label('Taille max')->options($clothes)->required(fn (Get $get) => $get('type') === 'vetement'),
                ]),
                TextInput::make('stock')->label('Stock pour chaque taille')->numeric()->integer()->minValue(0)->default(1)->required(),
            ])
            ->action(function (array $data, Get $get, Set $set) {
                $sizes = self::sizesInRange($data);
                if ($sizes->isEmpty()) {
                    Notification::make()->title('Aucune taille dans cet intervalle')->warning()->send();

                    return;
                }

                $colorId = filled($data['color_id'] ?? null) ? (string) $data['color_id'] : null;
                $rows = collect($get('variants') ?? [])
                    // retire les lignes vides (ni couleur, ni taille, stock 0)
                    ->reject(fn ($r) => blank($r['color_id'] ?? null) && blank($r['size_id'] ?? null) && (int) ($r['stock'] ?? 0) === 0);

                $added = [];
                $skipped = [];
                foreach ($sizes as $size) {
                    $exists = $rows->contains(fn ($r) => (string) ($r['color_id'] ?? '') === (string) $colorId && (string) ($r['size_id'] ?? '') === (string) $size->id);
                    if ($exists) {
                        $skipped[] = $size->name;

                        continue;
                    }
                    $rows->put((string) Str::uuid(), ['color_id' => $colorId, 'size_id' => (string) $size->id, 'stock' => (int) $data['stock']]);
                    $added[] = $size->name;
                }
                // Lignes rangées par couleur puis par taille (34, 35, 36…)
                $pos = Size::pluck('position', 'id');
                $colorPos = Color::pluck('position', 'id');
                $rows = $rows->sortBy(fn ($r) => sprintf('%05d-%05d',
                    filled($r['color_id'] ?? null) ? (int) ($colorPos[$r['color_id']] ?? 9999) : 99999,
                    filled($r['size_id'] ?? null) ? (int) ($pos[$r['size_id']] ?? 9999) : 99999));
                $set('variants', $rows->all());

                $color = $colorId ? Color::find($colorId)?->name : 'sans couleur';
                Notification::make()
                    ->title($added ? count($added).' taille(s) ajoutée(s) — '.$color : 'Rien à ajouter')
                    ->body(implode(' ', array_filter([
                        $added ? 'Tailles : '.implode(', ', $added).'.' : null,
                        $skipped ? 'Déjà présentes : '.implode(', ', $skipped).'.' : null,
                        $added ? 'Cliquez sur « Enregistrer » pour valider.' : null,
                    ])))
                    ->success()->send();
            });
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
