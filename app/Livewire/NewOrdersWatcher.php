<?php

namespace App\Livewire;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Component;

/**
 * Administration : surveille les nouvelles commandes du site (toutes les 10 s).
 * Affiche une notification avec un petit son et met à jour le compteur du menu,
 * sans recharger la page.
 */
class NewOrdersWatcher extends Component
{
    public int $lastId = 0;

    public function mount(): void
    {
        $this->lastId = (int) Order::max('id');
    }

    public function check(): void
    {
        $new = Order::where('id', '>', $this->lastId)->with('items')->orderBy('id')->get();
        if ($new->isEmpty()) {
            return;
        }
        $this->lastId = (int) $new->max('id');

        foreach ($new->take(3) as $order) {
            Notification::make()
                ->title('Nouvelle commande '.$order->number)
                ->body($order->customer_name.' · '.$order->city.' · '.money($order->total)."\n".$order->summary)
                ->icon('heroicon-o-shopping-bag')->iconColor('success')
                ->actions([Action::make('voir')->label('Voir la commande')->button()
                    ->url(OrderResource::getUrl('view', ['record' => $order]))])
                ->persistent()
                ->send();
        }
        if ($new->count() > 3) {
            Notification::make()->title('+'.($new->count() - 3).' autre(s) nouvelle(s) commande(s)')->success()->send();
        }

        $this->dispatch('mf-new-orders', pending: Order::status('en_attente')->count());
    }

    public function render()
    {
        return view('livewire.new-orders-watcher');
    }
}
