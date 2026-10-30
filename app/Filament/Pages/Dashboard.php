<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PendingOrders;
use App\Filament\Widgets\QuickLinks;
use App\Filament\Widgets\StockAlerts;
use App\Filament\Widgets\TodoOverview;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Accueil';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -10;

    public function getTitle(): string|Htmlable
    {
        return 'Bonjour, '.explode(' ', auth()->user()->name)[0];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return ucfirst(now()->translatedFormat('l j F')).' · voici ce qui demande votre attention aujourd’hui.';
    }

    public function getWidgets(): array
    {
        return [TodoOverview::class, PendingOrders::class, StockAlerts::class, QuickLinks::class];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 3];
    }
}
