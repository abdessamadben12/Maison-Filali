<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Stocks\StockResource;
use App\Models\ProductVariant;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StockAlerts extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(ProductVariant::query()->with(['product', 'color', 'size'])
                ->where('stock', '<=', config('shop.low_stock'))->orderBy('stock'))
            ->heading('Alertes stock')
            ->paginated([5])
            ->emptyStateHeading('Aucune alerte')
            ->emptyStateDescription('Tous les produits sont en stock.')
            ->recordUrl(fn () => StockResource::getUrl('index'))
            ->columns([
                ImageColumn::make('product.images')->label('')->state(fn (ProductVariant $r) => $r->product?->image_url)
                    ->imageWidth(34)->imageHeight(42),
                TextColumn::make('product.name')->label('Produit')
                    ->description(fn (ProductVariant $r) => $r->label.' · '.$r->stock.' pièce'.($r->stock > 1 ? 's' : '')),
                TextColumn::make('stock_status')->label('')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'rupture' ? 'Rupture' : 'Faible')
                    ->color(fn ($state) => $state === 'rupture' ? 'danger' : 'warning'),
            ]);
    }
}
