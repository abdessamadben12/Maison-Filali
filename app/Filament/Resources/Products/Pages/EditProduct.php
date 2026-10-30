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
