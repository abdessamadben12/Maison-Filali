<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /** Tableau couleurs × tailles, enregistré après la création du produit. */
    protected array $stockGrid = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->stockGrid = [
            (array) ($data['stock_colors'] ?? []),
            (array) ($data['stock_sizes'] ?? []),
            (array) ($data['stock_matrix'] ?? []),
        ];
        unset($data['stock_colors'], $data['stock_sizes'], $data['stock_matrix']);

        return $data;
    }

    protected function afterCreate(): void
    {
        ProductResource::syncVariants($this->record, ...$this->stockGrid);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
