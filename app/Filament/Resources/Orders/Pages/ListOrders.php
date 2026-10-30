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
        // Société de confirmation : ses deux listes de travail
        if (OrderResource::isConfirmateur()) {
            $mine = fn (Builder $q) => $q->where('handled_by_id', auth()->id());

            return [
                'en_attente' => Tab::make('À confirmer')->badgeColor('warning')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'en_attente')->orderBy('call_attempts')->orderBy('created_at'))
                    ->badge(fn () => Order::status('en_attente')->count() ?: null),
                'confirmees' => Tab::make('Confirmées par moi')->badgeColor('success')
                    ->modifyQueryUsing(fn (Builder $query) => $mine($query)->where('status', '!=', 'annulee')),
                'annulees' => Tab::make('Annulées par moi')->badgeColor('danger')
                    ->modifyQueryUsing(fn (Builder $query) => $mine($query)->where('status', 'annulee')),
            ];
        }

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
        if (OrderResource::isConfirmateur()) {
            return 'en_attente';
        }

        return Order::status('en_attente')->exists() ? 'en_attente' : 'toutes';
    }
}
