<?php

namespace App\Filament\Resources\Team;

use App\Filament\Resources\Team\Pages\ManageTeam;
use App\Models\Order;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin → Équipe : comptes d'accès à l'administration (gérant, société de confirmation).
 */
class TeamResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Équipe';

    protected static ?string $modelLabel = 'membre';

    protected static ?string $pluralModelLabel = 'équipe';

    protected static ?string $slug = 'equipe';

    protected static ?int $navigationSort = 19;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isGerant();
    }

    /** Le gérant ne peut pas supprimer son propre compte. */
    public static function canDelete(Model $record): bool
    {
        return $record->getKey() !== auth()->id();
    }

    public static function getEloquentQuery(): Builder
    {
        // Comptes de l'équipe : actuels (is_admin) ou désactivés (rôle conservé)
        return parent::getEloquentQuery()->where(fn (Builder $q) => $q->where('is_admin', true)->orWhereNotNull('role'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(80)->placeholder('Ex. : Société Confirm Plus — Sara'),
            TextInput::make('email')->label('E-mail de connexion')->email()->required()->maxLength(120)
                ->unique(ignoreRecord: true),
            TextInput::make('phone')->label('Téléphone')->tel()->maxLength(20),
            TextInput::make('password')->label('Mot de passe')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->minLength(8)->dehydrated(fn ($state) => filled($state))
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Laissez vide pour garder le mot de passe actuel.' : 'Au moins 8 caractères.'),
            Radio::make('role')->label('Rôle')->options(User::ROLES)->default('confirmateur')->required()->columnSpanFull()
                ->descriptions([
                    'gerant' => 'Produits, stocks, commandes (toutes les étapes), clients, paramètres, équipe.',
                    'confirmateur' => 'Voit uniquement les commandes à confirmer : appeler le client, confirmer, annuler ou noter « injoignable ». Ne voit ni les produits, ni les stocks, ni les paramètres.',
                ])
                ->disabled(fn (?User $record) => $record?->getKey() === auth()->id())
                ->dehydrated(fn (?User $record) => $record?->getKey() !== auth()->id()),
            Toggle::make('is_admin')->label('Compte actif (peut se connecter)')->default(true)
                ->disabled(fn (?User $record) => $record?->getKey() === auth()->id())
                ->dehydrated(fn (?User $record) => $record?->getKey() !== auth()->id()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'handledOrders as confirmed_count' => fn ($q) => $q->where('status', '!=', 'annulee'),
                'handledOrders as cancelled_count' => fn ($q) => $q->where('status', 'annulee'),
            ]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nom')->weight('medium')->searchable()
                    ->description(fn (User $r) => $r->email),
                TextColumn::make('role')->label('Rôle')->badge()
                    ->state(fn (User $r) => $r->role_label)
                    ->color(fn (User $r) => $r->isConfirmateur() ? 'warning' : 'success'),
                TextColumn::make('confirmed_count')->label('Confirmées')->alignCenter()->color('success'),
                TextColumn::make('cancelled_count')->label('Annulées')->alignCenter()->color('danger'),
                ToggleColumn::make('is_admin')->label('Actif')
                    ->disabled(fn (User $r) => $r->getKey() === auth()->id()),
            ])
            ->recordActions([
                EditAction::make()->label('')->tooltip('Modifier'),
                DeleteAction::make()->label('')->tooltip('Supprimer')
                    ->modalDescription('Le compte ne pourra plus se connecter. Les commandes traitées sont conservées.'),
            ])
            ->emptyStateHeading('Aucun membre');
    }

    public static function getPages(): array
    {
        return ['index' => ManageTeam::route('/')];
    }
}
