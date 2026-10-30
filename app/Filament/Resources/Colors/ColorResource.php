<?php

namespace App\Filament\Resources\Colors;

use App\Filament\Resources\Colors\Pages\ManageColors;
use App\Models\Color;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ColorResource extends Resource
{
    protected static ?string $model = Color::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $navigationLabel = 'Couleurs';

    protected static ?string $modelLabel = 'couleur';

    protected static ?string $pluralModelLabel = 'couleurs';

    protected static ?string $slug = 'couleurs';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(40),
            ColorPicker::make('hex')->label('Teinte')->required()->hex(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('variants.product'))
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                ColorColumn::make('hex')->label('')->width('40px'),
                TextColumn::make('name')->label('Couleur')->weight('medium')->searchable(),
                TextColumn::make('hex_code')->label('Code couleur')->state(fn (Color $r) => strtoupper($r->hex))->color('gray')->copyable(),
                TextColumn::make('used')->label('Utilisée par')->color('gray')->wrap()
                    ->state(fn (Color $r) => $r->variants->pluck('product.name')->filter()->unique()->implode(', ') ?: '—'),
            ])
            ->recordActions([
                EditAction::make()->label('')->tooltip('Modifier'),
                DeleteAction::make()->label('')->tooltip('Supprimer')
                    ->modalDescription('Les lignes de stock qui utilisent cette couleur la perdront.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageColors::route('/')];
    }
}
