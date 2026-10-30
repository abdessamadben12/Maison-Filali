<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Catégories';

    protected static ?string $modelLabel = 'catégorie';

    protected static ?string $pluralModelLabel = 'catégories';

    protected static ?string $slug = 'categories';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(80),
            TextInput::make('slug')->label('Lien')->prefix('/collection/')->unique(ignoreRecord: true)
                ->helperText('Laissez vide : rempli automatiquement.'),
            TextInput::make('description')->label('Description courte')->columnSpanFull()->maxLength(190),
            FileUpload::make('image')->label('Photo')->image()->disk('uploads')->directory('categories')->visibility('public')
                ->imageEditor()->maxSize(10240)->columnSpanFull(),
            Toggle::make('in_menu')->label('Afficher dans le menu du site'),
            Toggle::make('is_active')->label('En ligne')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('products'))
            ->defaultSort('position')
            ->reorderable('position')
            ->description('Glissez les lignes pour changer l’ordre d’affichage sur le site.')
            ->columns([
                ImageColumn::make('image')->label('')->disk('uploads')->imageWidth(44)->imageHeight(56),
                TextColumn::make('name')->label('Catégorie')->weight('medium')->description(fn (Category $r) => $r->description),
                TextColumn::make('slug')->label('Lien')->formatStateUsing(fn ($state) => '/collection/'.$state)->color('gray'),
                TextColumn::make('products_count')->label('Produits')->badge()
                    ->formatStateUsing(fn ($state) => $state ? $state.' produit'.($state > 1 ? 's' : '') : 'Aucun produit')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                ToggleColumn::make('in_menu')->label('Menu'),
                ToggleColumn::make('is_active')->label('En ligne'),
            ])
            ->recordActions([
                Action::make('site')->label('')->tooltip('Voir sur le site')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (Category $r) => route('category', $r), true),
                EditAction::make()->label('')->tooltip('Modifier'),
                DeleteAction::make()->label('')->tooltip('Supprimer')
                    ->disabled(fn (Category $r) => $r->products_count > 0)
                    ->tooltip(fn (Category $r) => $r->products_count > 0 ? 'Déplacez d’abord ses produits' : 'Supprimer'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
