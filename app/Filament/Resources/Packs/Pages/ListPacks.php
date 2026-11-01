<?php

namespace App\Filament\Resources\Packs\Pages;

use App\Filament\Resources\Packs\PackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPacks extends ListRecords
{
    protected static string $resource = PackResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouvelle offre')];
    }
}
