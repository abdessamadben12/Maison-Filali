<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Stocks\StockResource;
use App\Models\Order;
use App\Models\ProductVariant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Compteurs de tâches à faire (pas de statistiques de vente). */
class TodoOverview extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return (bool) auth()->user()?->isGerant();
    }

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $low = ProductVariant::whereBetween('stock', [1, config('shop.low_stock')])->count();
        $out = ProductVariant::where('stock', '<=', 0)->count();

        return [
            Stat::make('À confirmer', Order::status('en_attente')->count())
                ->description('commandes en attente — Traiter →')->color('warning')
                ->url(OrderResource::getUrl('index', ['tab' => 'en_attente'])),
            Stat::make('À expédier', Order::status('confirmee')->count())
                ->description('commandes confirmées — Voir →')->color('success')
                ->url(OrderResource::getUrl('index', ['tab' => 'confirmee'])),
            Stat::make('Stock faible', $low)
                ->description('variantes à réapprovisionner — Ajouter du stock →')->color('warning')
                ->url(StockResource::getUrl('index', ['tab' => 'faible'])),
            Stat::make('Rupture', $out)
                ->description('variantes non commandables — Voir →')->color('danger')
                ->url(StockResource::getUrl('index', ['tab' => 'rupture'])),
        ];
    }
}
