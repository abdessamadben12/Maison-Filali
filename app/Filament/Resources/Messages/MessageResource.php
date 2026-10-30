<?php

namespace App\Filament\Resources\Messages;

use App\Filament\Resources\Messages\Pages\ManageMessages;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Messages';

    protected static ?string $modelLabel = 'message';

    protected static ?string $pluralModelLabel = 'messages';

    protected static ?string $slug = 'messages';

    protected static ?int $navigationSort = 8;

    public static function getNavigationBadge(): ?string
    {
        $n = ContactMessage::where('is_read', false)->count();

        return $n ? (string) $n : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('name')->label('Nom'),
            TextEntry::make('phone')->label('Téléphone')->formatStateUsing(fn ($state) => format_phone($state))
                ->url(fn (ContactMessage $r) => 'tel:'.$r->phone)->color('primary'),
            TextEntry::make('email')->label('E-mail')->placeholder('—')->url(fn (ContactMessage $r) => $r->email ? 'mailto:'.$r->email : null),
            TextEntry::make('subject_label')->label('Sujet')->badge(),
            TextEntry::make('message')->label('Message')->columnSpanFull()->prose(),
            TextEntry::make('created_at')->label('Reçu le')->dateTime('d/m/Y à H:i'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $markRead = fn (ContactMessage $r) => $r->is_read ?: $r->update(['is_read' => true]);

        return $table
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Nom, téléphone, message…')
            ->columns([
                IconColumn::make('is_read')->label('')->boolean()->trueIcon(Heroicon::OutlinedEnvelopeOpen)->falseIcon(Heroicon::Envelope)
                    ->trueColor('gray')->falseColor('warning')->width('40px'),
                TextColumn::make('name')->label('De')->searchable()->weight(fn (ContactMessage $r) => $r->is_read ? 'normal' : 'bold')
                    ->description(fn (ContactMessage $r) => format_phone($r->phone)),
                TextColumn::make('subject_label')->label('Sujet')->badge()->color('gray'),
                TextColumn::make('message')->label('Message')->limit(70)->searchable()->wrap(),
                TextColumn::make('created_at')->label('Reçu')->since()->sortable()->color('gray'),
            ])
            ->filters([TernaryFilter::make('is_read')->label('Lu')->trueLabel('Lus')->falseLabel('Non lus')])
            ->recordActions([
                ViewAction::make()->label('Lire')->modalHeading(fn (ContactMessage $r) => 'Message de '.$r->name)
                    ->mountUsing(fn (ContactMessage $record) => $markRead($record))
                    ->extraModalFooterActions([
                        Action::make('wa')->label('Répondre sur WhatsApp')->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                            ->url(fn (ContactMessage $r) => 'https://wa.me/212'.substr($r->phone, 1).'?text='.rawurlencode('Bonjour '.explode(' ', $r->name)[0].', ici Maison Filali. '), true),
                    ]),
                DeleteAction::make()->label('')->tooltip('Supprimer'),
            ])
            ->emptyStateHeading('Aucun message')
            ->emptyStateDescription('Les messages du formulaire de contact du site apparaissent ici.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageMessages::route('/')];
    }
}
