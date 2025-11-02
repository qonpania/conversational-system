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
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ActionGroup;

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

                Tables\Columns\TextColumn::make('summary')
                    ->label('Resumen')
                    ->limit(80)
                    ->tooltip(fn($record) => $record->summary)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('summary_updated_at')
                    ->label('Resumido')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('metrics.sentiment_overall')->label('Sent.')->badge(),
                Tables\Columns\TextColumn::make('metrics.csat_pred')
                  ->label('CSAT')
                  ->formatStateUsing(fn($record) => is_null($record->metrics->csat_pred) ? '—' : (int)round($record->metrics->csat_pred*100).'%' ),
                Tables\Columns\TextColumn::make('metrics.churn_risk')
                  ->label('Churn')
                  ->formatStateUsing(fn($record) => is_null($record->metrics->churn_risk) ? '—' : (int)round($record->metrics->churn_risk*100).'%' ),

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

                Tables\Filters\SelectFilter::make('metrics.sentiment_overall')
                    ->label('Sentimiento')
                    ->options(['positive'=>'Positivo','neutral'=>'Neutral','negative'=>'Negativo'])
                    ->query(function(Builder $q, array $data) {
                        if (!($data['value'] ?? null)) return $q;
                        return $q->whereHas('metrics', fn($qq) => $qq->where('sentiment_overall', $data['value']));
                    }),
            ])
            ->actions([
                ActionGroup::make([

                    Tables\Actions\ViewAction::make(),

                    Tables\Actions\Action::make('takeover')
                        ->label('Tomar (Humano)')
                        ->icon('heroicon-o-user')
                        ->color('warning')
                        ->visible(fn($record) => $record->routing_mode !== 'human')
                        ->requiresConfirmation()
                        ->action(function($record){
                            $record->update([
                                'routing_mode'    => 'human',
                                'assigned_user_id'=> Auth::id(),
                                'handover_at'     => now(),
                            ]);
                            Notification::make()->title('Tomaste la conversación')->success()->send();
                        }),

                    Tables\Actions\Action::make('resumeAi')
                        ->label('Volver a IA')
                        ->icon('heroicon-o-cpu-chip')
                        ->color('success')
                        ->visible(fn($record) => $record->routing_mode !== 'ai')
                        ->requiresConfirmation()
                        ->action(function($record){
                            $record->update([
                                'routing_mode'    => 'ai',
                                'assigned_user_id'=> null,
                                'resume_ai_at'    => now(),
                            ]);
                            Notification::make()->title('La IA volvió a tomar la conversación')->success()->send();
                        }),

                    ]),
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
