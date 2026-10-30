<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class QuickLinks extends Widget
{
    public static function canView(): bool
    {
        return (bool) auth()->user()?->isGerant();
    }

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 1, 'lg' => 3];

    protected string $view = 'filament.widgets.quick-links';
}
