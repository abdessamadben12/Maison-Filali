<?php

namespace App\Filament\Resources\Stocks;

use App\Filament\Resources\Stocks\Pages\ManageStocks;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use RuntimeException;

class StockResource extends Resource
{
    protected static ?string $model = ProductVariant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Stocks';

    protected static ?string $modelLabel = 'stock';

    protected static ?string $pluralModelLabel = 'stocks';

    protected static ?string $slug = 'stocks';

    protected static ?int $navigationSort = 6;

    public static function getNavigationBadge(): ?string
    {
        $n = ProductVariant::where('stock', '<=', config('shop.low_stock'))->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product.category', 'color', 'size'])
                ->whereHas('product')
                ->orderBy(Product::select('position')->whereColumn('products.id', 'product_variants.product_id'))
                ->orderBy('color_id')->orderBy('size_id'))
            ->searchPlaceholder('Produit, couleur, taille…')
            ->defaultPaginationPageOption(25)
            ->columns([
                ImageColumn::make('photo')->label('')->state(fn (ProductVariant $r) => $r->product?->image_url)->imageWidth(40)->imageHeight(50),
                TextColumn::make('product.name')->label('Produit')->weight('medium')->searchable()
                    ->description(fn (ProductVariant $r) => $r->product?->category?->name),
                TextColumn::make('variant')->label('Variante')
                    ->state(fn (ProductVariant $r) => new HtmlString(
                        ($r->color ? '<span class="mf-swatch" style="background:'.e($r->color->hex).'"></span>' : '').e($r->label)
                    ))
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas('color', fn ($c) => $c->where('name', 'like', "%$search%"))
                        ->orWhereHas('size', fn ($s) => $s->where('name', $search))),
                TextInputColumn::make('stock')->label('Quantité')->type('number')->rules(['required', 'integer', 'min:0'])->width('120px')
                    ->updateStateUsing(function (ProductVariant $record, $state) {
                        $diff = (int) $state - $record->stock;
                        if ($diff !== 0) {
                            try {
                                $record->adjustStock($diff, 'correction', 'Modifié depuis la page Stocks');
                            } catch (RuntimeException $e) {
                                Notification::make()->title($e->getMessage())->danger()->send();
                                $record->refresh();
                            }
                        }

                        return $record->stock;
                    }),
                TextColumn::make('stock_status')->label('Statut')->badge()
                    ->formatStateUsing(fn ($state) => ['ok' => 'En stock', 'faible' => 'Stock faible', 'rupture' => 'Rupture'][$state])
                    ->color(fn ($state) => ['ok' => 'success', 'faible' => 'warning', 'rupture' => 'danger'][$state]),
                TextColumn::make('updated_at')->label('Mis à jour')->since()->alignEnd()->color('gray'),
            ])
            ->filters([
                SelectFilter::make('product_id')->label('Produit')->relationship('product', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('add')->label('Ajouter')->icon(Heroicon::OutlinedPlus)->color('primary')
                    ->modalHeading(fn (ProductVariant $r) => 'Ajouter du stock — '.$r->full_label)
                    ->modalWidth('lg')
                    ->schema(fn (ProductVariant $r) => static::movementFields($r->stock))
                    ->action(function (ProductVariant $record, array $data) {
                        $record->adjustStock((int) $data['quantity'], $data['reason'], $data['note'] ?? null);
                        Notification::make()->title('+'.$data['quantity'].' pièce(s) — nouveau stock : '.$record->stock)->success()->send();
                    }),
                Action::make('history')->label('')->tooltip('Historique')->icon(Heroicon::OutlinedClock)->color('gray')
                    ->modalHeading(fn (ProductVariant $r) => 'Historique — '.$r->full_label)
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fermer')
                    ->modalContent(fn (ProductVariant $r) => view('filament.stock-history', [
                        'movements' => $r->movements()->with('user')->latest()->take(30)->get(),
                    ])),
            ])
            ->emptyStateHeading('Aucune ligne de stock')
            ->emptyStateDescription('Ajoutez des couleurs / tailles depuis la fiche d’un produit.');
    }

    /** Champs du formulaire « Ajouter du stock ». */
    public static function movementFields(?int $currentStock = null): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('quantity')->label('Quantité à ajouter')->numeric()->integer()->minValue(1)->default(1)->required()->live(),
                Select::make('reason')->label('Motif')->default('reception_atelier')->required()
                    ->options(collect(StockMovement::REASONS)->only(['reception_atelier', 'retour_client', 'correction'])->all()),
            ]),
            TextInput::make('note')->label('Note (facultatif)')->placeholder('Ex. : lot livré par l’atelier de Fès'),
            Placeholder::make('preview')->hiddenLabel()->visible($currentStock !== null)
                ->content(fn (Get $get) => new HtmlString('<div style="background:#f3eadb;padding:14px 18px;border-radius:8px;display:flex;gap:18px;align-items:center">'
                    .'<span>Stock actuel <b style="font-size:1.4rem">'.$currentStock.'</b></span><span style="color:#c6a15b">→</span>'
                    .'<span>Nouveau stock <b style="font-size:1.4rem;color:#0f4a3e">'.($currentStock + max(0, (int) $get('quantity'))).'</b></span></div>')),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => ManageStocks::route('/')];
    }
}
