<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** Tableau couleurs × tailles, enregistré après le produit. */
    protected array $stockGrid = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, ProductResource::variantsToForm($this->record));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->stockGrid = [
            (array) ($data['stock_colors'] ?? []),
            (array) ($data['stock_sizes'] ?? []),
            (array) ($data['stock_matrix'] ?? []),
        ];
        unset($data['stock_colors'], $data['stock_sizes'], $data['stock_matrix']);

        return $data;
    }

    protected function afterSave(): void
    {
        ProductResource::syncVariants($this->record, ...$this->stockGrid);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('site')->label('Voir sur le site')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn () => route('product', $this->record), true),
            DeleteAction::make(),
        ];
    }
}
