<?php

namespace App\Filament\Resources\Packs\Pages;

use App\Filament\Resources\Packs\PackResource;
use App\Models\Pack;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditPack extends EditRecord
{
    protected static string $resource = PackResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    protected function afterSave(): void
    {
        Pack::forgetCache();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('site')->label('Voir sur le site')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn () => route('packs.show', $this->record), true)->visible(fn () => $this->record->isAvailable()),
            DeleteAction::make(),
        ];
    }
}
