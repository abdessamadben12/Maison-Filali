<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use RuntimeException;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Commandes';

    protected static ?string $modelLabel = 'commande';

    protected static ?string $pluralModelLabel = 'commandes';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $slug = 'commandes';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $n = Order::status('en_attente')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'customer_name', 'phone'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('items.product', 'items.variant'))
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('N°, nom, téléphone…')
            ->recordUrl(fn (Order $r) => static::getUrl('view', ['record' => $r]))
            ->columns([
                ImageColumn::make('photo')->label('')->state(fn (Order $r) => $r->items->first()?->product?->image_url)
                    ->imageWidth(44)->imageHeight(56),
                TextColumn::make('number')->label('N°')->weight('medium')->searchable()->sortable()
                    ->description(fn (Order $r) => $r->created_at->diffForHumans()),
                TextColumn::make('customer_name')->label('Client')->searchable(['customer_name', 'phone'])
                    ->description(fn (Order $r) => $r->city.' · '.format_phone($r->phone)),
                TextColumn::make('summary')->label('Article')->state(fn (Order $r) => $r->summary)
                    ->description(fn (Order $r) => ($r->items->first()?->variant_label ?? '').' · ×'.$r->items->sum('quantity')),
                TextColumn::make('total')->label('Montant')->sortable()->formatStateUsing(fn ($state) => money($state))
                    ->color('primary')->weight('medium'),
                TextColumn::make('status')->label('Statut')->badge()
                    ->formatStateUsing(fn ($state) => Order::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => Order::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('city')->label('Ville')->options(fn () => Order::query()->distinct()->orderBy('city')->pluck('city', 'city')->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Voir'),
                ActionGroup::make(static::statusActions()),
            ])
            ->emptyStateHeading('Aucune commande')
            ->emptyStateDescription('Les commandes passées sur le site apparaissent ici.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Section::make('Articles')->columnSpan(['lg' => 2])->schema([
                    RepeatableEntry::make('items')->hiddenLabel()->contained(false)->schema([
                        Grid::make(['default' => 4])->schema([
                            ImageEntry::make('photo')->hiddenLabel()->state(fn ($record) => $record->product?->image_url)
                                ->imageWidth(64)->imageHeight(84),
                            TextEntry::make('product_name')->hiddenLabel()->weight('medium')->size('lg')->columnSpan(2)
                                ->helperText(fn ($record) => $record->variant_label.' · Qté '.$record->quantity),
                            TextEntry::make('total')->hiddenLabel()->formatStateUsing(fn ($state) => money($state))
                                ->color('primary')->weight('bold')->alignEnd(),
                        ]),
                        TextEntry::make('stock_info')->hiddenLabel()->state(function ($record) {
                            if ($record->is_custom) {
                                return 'Pièce sur mesure : délai et mesures à convenir avec la cliente.';
                            }
                            if (! $record->variant) {
                                return 'Variante supprimée du catalogue.';
                            }

                            return 'Stock disponible : '.$record->variant->stock.' pièce(s) ('.$record->variant->label.')';
                        })->color(fn ($record) => $record->variant && $record->variant->stock >= $record->quantity ? 'success' : 'warning')
                            ->icon(Heroicon::OutlinedCube)
                            ->visible(fn ($record) => in_array($record->order->status, ['en_attente'], true)),
                    ]),
                    TextEntry::make('total')->label('Total · paiement à la livraison')->formatStateUsing(fn ($state) => money($state))
                        ->size('lg')->weight('bold')->color('primary'),
                ]),
                Section::make('Cliente / client')->columnSpan(1)->schema([
                    TextEntry::make('status')->label('Statut actuel')->badge()
                        ->formatStateUsing(fn ($state) => Order::STATUSES[$state] ?? $state)
                        ->color(fn ($state) => Order::STATUS_COLORS[$state] ?? 'gray'),
                    TextEntry::make('customer_name')->label('Nom'),
                    TextEntry::make('phone')->label('Téléphone')->formatStateUsing(fn ($state) => format_phone($state))
                        ->url(fn (Order $r) => 'tel:'.$r->phone)->color('primary'),
                    TextEntry::make('whatsapp')->label('WhatsApp')->state('Écrire au client')
                        ->url(fn (Order $r) => 'https://wa.me/212'.substr($r->phone, 1).'?text='.rawurlencode('Bonjour '.$r->first_name.', Maison Filali au sujet de votre commande '.$r->number.'.'), true)
                        ->color('primary'),
                    TextEntry::make('city')->label('Ville'),
                    TextEntry::make('address')->label('Adresse')->placeholder('—'),
                    TextEntry::make('user_id')->label('Compte client')->formatStateUsing(fn ($state) => $state ? 'Oui' : 'Non')->placeholder('Sans compte'),
                    TextEntry::make('created_at')->label('Reçue le')->dateTime('d/m/Y à H:i'),
                    TextEntry::make('admin_note')->label('Note interne')->placeholder('Aucune note')
                        ->formatStateUsing(fn ($state) => new HtmlString(nl2br(e($state)))),
                ]),
            ]),
        ]);
    }

    /** Actions de changement de statut (tableau + page de détail). */
    public static function statusActions(): array
    {
        $run = function (Order $record, string $method, string $ok) {
            try {
                $record->load('items.variant');
                $record->{$method}();
                Notification::make()->title($ok)->success()->send();
            } catch (RuntimeException $e) {
                Notification::make()->title('Action impossible')->body($e->getMessage())->danger()->persistent()->send();
            }
        };

        return [
            Action::make('confirm')->label('Confirmer la commande')->icon(Heroicon::Check)->color('success')
                ->visible(fn (Order $r) => $r->status === 'en_attente')
                ->requiresConfirmation()->modalHeading(fn (Order $r) => 'Confirmer '.$r->number.' ?')
                ->modalDescription('Le stock sera déduit et le client verra « Confirmée » sur le site.')
                ->modalSubmitActionLabel('Confirmer')
                ->action(fn (Order $record) => $run($record, 'confirm', 'Commande confirmée')),
            Action::make('ship')->label('Marquer expédiée')->icon(Heroicon::OutlinedTruck)->color('info')
                ->visible(fn (Order $r) => $r->status === 'confirmee')
                ->requiresConfirmation()->modalSubmitActionLabel('Expédiée')
                ->action(fn (Order $record) => $run($record, 'ship', 'Commande marquée expédiée')),
            Action::make('deliver')->label('Marquer livrée')->icon(Heroicon::OutlinedCheckBadge)->color('gray')
                ->visible(fn (Order $r) => $r->status === 'expediee')
                ->requiresConfirmation()->modalSubmitActionLabel('Livrée')
                ->action(fn (Order $record) => $run($record, 'deliver', 'Commande marquée livrée')),
            Action::make('note')->label('Note interne')->icon(Heroicon::OutlinedPencilSquare)->color('gray')
                ->fillForm(fn (Order $r) => ['admin_note' => $r->admin_note])
                ->schema([Textarea::make('admin_note')->label('Note interne')->placeholder('Ex. : client appelé, livraison jeudi…')->rows(4)])
                ->action(function (Order $record, array $data) {
                    $record->update(['admin_note' => $data['admin_note']]);
                    Notification::make()->title('Note enregistrée')->success()->send();
                }),
            Action::make('cancel')->label('Annuler la commande')->icon(Heroicon::OutlinedXMark)->color('danger')
                ->visible(fn (Order $r) => ! in_array($r->status, ['livree', 'annulee'], true))
                ->requiresConfirmation()->modalHeading(fn (Order $r) => 'Annuler '.$r->number.' ?')
                ->modalDescription('Si le stock avait été déduit, il sera remis automatiquement.')
                ->modalSubmitActionLabel('Annuler la commande')->modalCancelActionLabel('Retour')
                ->action(fn (Order $record) => $run($record, 'cancel', 'Commande annulée')),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
