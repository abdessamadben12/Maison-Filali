<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomers extends ManageRecords
{
    protected static string $resource = CustomerResource::class;

    public function getSubheading(): ?string
    {
        return 'Chaque personne qui a commandé, avec ou sans compte (reconnue par son téléphone).';
    }
}
