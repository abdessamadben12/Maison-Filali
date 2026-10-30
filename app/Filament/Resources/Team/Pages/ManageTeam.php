<?php

namespace App\Filament\Resources\Team\Pages;

use App\Filament\Resources\Team\TeamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTeam extends ManageRecords
{
    protected static string $resource = TeamResource::class;

    public function getSubheading(): ?string
    {
        return 'Créez ici les comptes de la société de confirmation : ils verront uniquement les commandes à confirmer.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Ajouter un membre')->icon('heroicon-o-plus')];
    }
}
