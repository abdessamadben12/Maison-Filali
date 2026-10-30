<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use RuntimeException;

class PendingOrders extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 1, 'lg' => 2];

    public function getColumnSpan(): int|string|array
    {
        return auth()->user()?->isConfirmateur() ? 'full' : $this->columnSpan;
    }

    protected static ?string $heading = 'Commandes à confirmer';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->status('en_attente')->with('items.product')->latest())
            ->heading('Commandes à confirmer')
            ->poll('10s')
            ->paginated([5])
            ->emptyStateHeading('Aucune commande en attente')
            ->emptyStateDescription('Les nouvelles commandes du site apparaîtront ici.')
            ->recordUrl(fn (Order $r) => OrderResource::getUrl('view', ['record' => $r]))
            ->columns([
                TextColumn::make('number')->label('Commande')->weight('medium')
                    ->description(fn (Order $r) => $r->created_at->diffForHumans()),
                TextColumn::make('customer_name')->label('Client')
                    ->description(fn (Order $r) => $r->city.' · '.format_phone($r->phone)),
                TextColumn::make('summary')->label('Article')->state(fn (Order $r) => $r->summary)->wrap()
                    ->description(fn (Order $r) => $r->items->first()?->variant_label),
                TextColumn::make('total')->label('Montant')->formatStateUsing(fn ($state) => money($state))->color('primary')->weight('medium'),
            ])
            ->recordActions([
                Action::make('confirm')->label('Confirmer')->icon(Heroicon::Check)->button()->size('sm')
                    ->requiresConfirmation()->modalHeading(fn (Order $r) => 'Confirmer '.$r->number.' ?')
                    ->modalDescription('Le stock sera déduit et le client verra « Confirmée » sur le site.')
                    ->action(function (Order $record) {
                        try {
                            $record->load('items.variant')->confirm();
                            Notification::make()->title('Commande '.$record->number.' confirmée')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Impossible de confirmer')->body($e->getMessage())->danger()->persistent()->send();
                        }
                    }),
            ]);
    }
}
