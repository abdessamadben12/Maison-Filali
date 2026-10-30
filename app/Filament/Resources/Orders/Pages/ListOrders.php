<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        $counts = Order::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $tab = fn (string $label, string $status) => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status))
            ->badge($counts[$status] ?? null)
            ->badgeColor(Order::STATUS_COLORS[$status]);

        return [
            'en_attente' => $tab('À confirmer', 'en_attente'),
            'confirmee' => $tab('Confirmées', 'confirmee'),
            'expediee' => $tab('Expédiées', 'expediee'),
            'livree' => $tab('Livrées', 'livree'),
            'annulee' => $tab('Annulées', 'annulee'),
            'toutes' => Tab::make('Toutes'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return Order::status('en_attente')->exists() ? 'en_attente' : 'toutes';
    }
}
