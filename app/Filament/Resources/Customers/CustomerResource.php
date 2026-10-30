<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Customer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Clients';

    protected static ?string $modelLabel = 'client';

    protected static ?string $pluralModelLabel = 'clients';

    protected static ?string $slug = 'clients';

    protected static ?int $navigationSort = 7;

    /** Réservé au gérant (la société de confirmation n'y a pas accès). */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isGerant();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nom')->required(),
            TextInput::make('phone')->label('Téléphone')->disabled()->formatStateUsing(fn ($state) => format_phone($state)),
            Select::make('city')->label('Ville')->options(array_combine(config('shop.cities'), config('shop.cities'))),
            TextInput::make('email')->label('E-mail')->email(),
            TextInput::make('address')->label('Adresse')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('orders')->withMax('orders', 'created_at'))
            ->defaultSort('orders_max_created_at', 'desc')
            ->searchPlaceholder('Nom, téléphone, ville…')
            ->columns([
                TextColumn::make('name')->label('Client')->weight('medium')->searchable()
                    ->description(fn (Customer $r) => $r->user_id ? ($r->email ?: 'Compte client') : 'Sans compte'),
                TextColumn::make('phone')->label('Téléphone')->searchable()->formatStateUsing(fn ($state) => format_phone($state))
                    ->url(fn (Customer $r) => 'tel:'.$r->phone),
                TextColumn::make('city')->label('Ville')->searchable()->sortable(),
                TextColumn::make('orders_count')->label('Commandes')->sortable()->weight('medium'),
                TextColumn::make('orders_max_created_at')->label('Dernière commande')->since()->sortable()->color('gray'),
            ])
            ->filters([
                SelectFilter::make('city')->label('Ville')->options(fn () => Customer::query()->distinct()->orderBy('city')->pluck('city', 'city')->filter()->all()),
            ])
            ->recordActions([
                Action::make('orders')->label('Voir les commandes')->icon(Heroicon::OutlinedChevronRight)->iconPosition('after')->link()
                    ->url(fn (Customer $r) => OrderResource::getUrl('index', ['tab' => 'toutes', 'search' => $r->phone])),
                Action::make('whatsapp')->label('')->tooltip('WhatsApp')->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)->color('gray')
                    ->url(fn (Customer $r) => 'https://wa.me/212'.substr($r->phone, 1), true),
                EditAction::make()->label('')->tooltip('Modifier'),
            ])
            ->emptyStateHeading('Aucun client pour l’instant');
    }

    public static function getPages(): array
    {
        return ['index' => ManageCustomers::route('/')];
    }
}
