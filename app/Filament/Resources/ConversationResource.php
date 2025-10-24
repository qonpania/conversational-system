<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConversationResource\Pages;
use App\Filament\Resources\ConversationResource\RelationManagers;
use App\Models\Conversation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Atención Omnicanal';
    protected static ?string $navigationLabel = 'Conversaciones';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_message_at','desc')
            ->columns([
                Tables\Columns\TextColumn::make('channel.name')->label('Canal')->badge(),
                Tables\Columns\TextColumn::make('contact.username')->label('Usuario')->searchable(),
                Tables\Columns\TextColumn::make('contact.name')->label('Nombre')->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['success' => 'closed','warning' => 'snoozed','primary' => 'open'])
                    ->label('Estado'),
                Tables\Columns\TextColumn::make('last_message_at')->dateTime('Y-m-d H:i')->label('Último'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel_id')
                    ->label('Canal')
                    ->relationship('channel', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Abierta',
                        'closed' => 'Cerrada',
                        'snoozed' => 'En pausa',
                    ]),

                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde'),
                        Forms\Components\DatePicker::make('to')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('last_message_at', '>=', $v))
                            ->when($data['to'] ?? null,   fn (Builder $q, $v) => $q->whereDate('last_message_at', '<=', $v));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(), 
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
            // 'create' => Pages\CreateConversation::route('/create'),
            'view' => Pages\ViewConversation::route('/{record}'),
            // 'edit' => Pages\EditConversation::route('/{record}/edit'),
        ];
    }
}
