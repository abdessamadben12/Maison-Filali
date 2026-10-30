<?php

namespace App\Filament\Resources\Sizes;

use App\Filament\Resources\Sizes\Pages\ManageSizes;
use App\Models\Size;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SizeResource extends Resource
{
    protected static ?string $model = Size::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencil;

    protected static ?string $navigationLabel = 'Tailles';

    protected static ?string $modelLabel = 'taille';

    protected static ?string $pluralModelLabel = 'tailles';

    protected static ?string $slug = 'tailles';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Taille')->required()->maxLength(20)->placeholder('M ou 38'),
            Select::make('type')->label('Type')->options(Size::TYPES)->default('vetement')->required(),
            TextInput::make('guide')->label('Guide des tailles')->columnSpanFull()->placeholder('Ex. : Tour de poitrine 89 – 94 cm')
                ->helperText('Affiché sur la page « Guide des tailles » du site.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('variants.product'))
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('name')->label('Taille')->weight('bold')->badge()->color('gray'),
                TextColumn::make('guide')->label('Guide des tailles')->placeholder('—'),
                TextColumn::make('used')->label('Utilisée par')->color('gray')->wrap()
                    ->state(fn (Size $r) => $r->variants->pluck('product.name')->filter()->unique()->implode(', ') ?: '—'),
            ])
            ->recordActions([
                EditAction::make()->label('')->tooltip('Modifier'),
                DeleteAction::make()->label('')->tooltip('Supprimer'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSizes::route('/')];
    }
}
