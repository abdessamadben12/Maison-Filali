<?php

namespace App\Filament\Resources\Packs\Pages;

use App\Filament\Resources\Packs\PackResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePack extends CreateRecord
{
    protected static string $resource = PackResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
