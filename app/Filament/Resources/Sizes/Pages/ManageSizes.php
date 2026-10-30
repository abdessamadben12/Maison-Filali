<?php

namespace App\Filament\Resources\Sizes\Pages;

use App\Filament\Resources\Sizes\SizeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageSizes extends ManageRecords
{
    protected static string $resource = SizeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Ajouter une taille')->icon('heroicon-o-plus')
                ->fillForm(fn () => ['type' => $this->activeTab === 'pointure' ? 'pointure' : 'vetement']),
        ];
    }

    public function getTabs(): array
    {
        return [
            'vetement' => Tab::make('Vêtements')->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'vetement'))
                ->badge(fn () => \App\Models\Size::where('type', 'vetement')->count()),
            'pointure' => Tab::make('Pointures')->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'pointure'))
                ->badge(fn () => \App\Models\Size::where('type', 'pointure')->count()),
        ];
    }
}
