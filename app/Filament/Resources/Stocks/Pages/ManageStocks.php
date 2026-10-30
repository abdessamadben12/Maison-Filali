<?php

namespace App\Filament\Resources\Stocks\Pages;

use App\Filament\Resources\Stocks\StockResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;

class ManageStocks extends ManageRecords
{
    protected static string $resource = StockResource::class;

    public function getSubheading(): ?string
    {
        return 'Stock faible = '.config('shop.low_stock').' pièces ou moins. Une variante en rupture n’est plus commandable sur le site. Le stock est déduit quand vous confirmez une commande.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStock')->label('Ajouter du stock')->icon('heroicon-o-plus')
                ->modalHeading('Ajouter du stock')->modalDescription('Réception de l’atelier ou retour d’un client')
                ->modalWidth('lg')->modalSubmitActionLabel('Ajouter au stock')
                ->schema([
                    Select::make('product_id')->label('Produit')->required()->searchable()->preload()->live()
                        ->options(Product::orderBy('position')->pluck('name', 'id'))
                        ->afterStateUpdated(fn (Set $set) => $set('variant_id', null)),
                    Select::make('variant_id')->label('Couleur / taille')->required()->live()
                        ->options(fn (Get $get) => ProductVariant::with('color', 'size')->where('product_id', $get('product_id'))->get()
                            ->mapWithKeys(fn ($v) => [$v->id => $v->label.' — '.$v->stock.' en stock'])->all())
                        ->disabled(fn (Get $get) => ! $get('product_id')),
                    ...StockResource::movementFields(),
                ])
                ->action(function (array $data) {
                    $variant = ProductVariant::findOrFail($data['variant_id']);
                    $variant->adjustStock((int) $data['quantity'], $data['reason'], $data['note'] ?? null);
                    Notification::make()->title('Stock mis à jour')
                        ->body($variant->load('product')->full_label.' : '.$variant->stock.' pièce(s)')->success()->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        $low = config('shop.low_stock');

        return [
            'tous' => Tab::make('Tous')->badge(fn () => ProductVariant::whereHas('product')->count()),
            'faible' => Tab::make('Stock faible')->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('stock', [1, $low]))
                ->badge(fn () => ProductVariant::whereBetween('stock', [1, $low])->count()),
            'rupture' => Tab::make('Rupture')->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('stock', '<=', 0))
                ->badge(fn () => ProductVariant::where('stock', '<=', 0)->count()),
        ];
    }
}
