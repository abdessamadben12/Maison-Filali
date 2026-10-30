<x-filament-widgets::widget>
    <x-filament::section heading="Raccourcis">
        <div style="display:flex;flex-wrap:wrap;gap:12px">
            <x-filament::button tag="a" :href="\App\Filament\Resources\Products\ProductResource::getUrl('create')" icon="heroicon-o-plus" outlined>
                Ajouter un produit
            </x-filament::button>
            <x-filament::button tag="a" :href="\App\Filament\Resources\Stocks\StockResource::getUrl('index')" icon="heroicon-o-cube" outlined>
                Ajouter du stock
            </x-filament::button>
            <x-filament::button tag="a" :href="url('/')" target="_blank" icon="heroicon-o-arrow-top-right-on-square" outlined>
                Voir le site
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
