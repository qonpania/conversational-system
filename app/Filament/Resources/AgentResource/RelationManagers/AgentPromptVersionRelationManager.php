<?php

// app/Filament/Resources/AgentResource/RelationManagers/PromptsRelationManager.php
namespace App\Filament\Resources\AgentResource\RelationManagers;

use App\Models\AgentPromptVersion;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Components\MarkdownEditor;

class PromptsRelationManager extends RelationManager
{
    protected static string $relationship = 'prompts';
    protected static ?string $title = 'Versiones de Prompt';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->required()->maxLength(160),
            MarkdownEditor::make('content')
                ->label('Prompt (Markdown)')
                ->required()
                ->minHeight('28rem')               // alto cómodo
                ->maxLength(20000)                 // pon tu límite
                ->toolbarButtons([
                    'heading', 'bold', 'italic', 'strike', 'link',
                    'blockquote', 'codeBlock', 'orderedList', 'bulletList',
                    'horizontalRule', 'undo', 'redo',
                ])
                ->placeholder("# Rol\nEres un agente…\n\n## Instrucciones\n- …\n\n```json\n{\"ejemplo\": true}\n```")
                ->helperText('Soporta Markdown y bloques de código (```lang).'),

            Forms\Components\KeyValue::make('parameters')
                ->keyLabel('param')->valueLabel('valor')->addButtonLabel('Añadir')
                ->helperText('Ej: temperature=0.3, top_p=1, system=...'),
            Forms\Components\Select::make('status')
                ->options(['draft'=>'Borrador','published'=>'Publicado','archived'=>'Archivado'])
                ->default('draft'),
            Forms\Components\Textarea::make('notes')->rows(3)->label('Notas / Changelog'),
        ])->columns(1);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('version')->badge()->sortable(),
                Tables\Columns\TextColumn::make('title')->limit(40)->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['primary'=>'draft','success'=>'published','gray'=>'archived']),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Activo'),
                Tables\Columns\TextColumn::make('activated_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->since(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function(array $data): array {
                        // siempre entran como draft; activar es acción aparte
                        $data['status'] = $data['status'] ?? 'draft';
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('activate')
                    ->label('Activar')
                    ->icon('heroicon-o-bolt')
                    ->requiresConfirmation()
                    ->visible(fn(AgentPromptVersion $r) => !$r->is_active)
                    ->action(function(AgentPromptVersion $record){
                        $record->activate();
                    }),
                Tables\Actions\Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->label('Duplicar')
                    ->action(function(AgentPromptVersion $record){
                        $record->replicate([
                            'version','is_active','activated_at','status'
                        ])->fill([
                            'status'=>'draft',
                            'is_active'=>false,
                            'activated_at'=>null,
                        ])->save();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn(AgentPromptVersion $r) => !$r->is_active),
            ])
            ->defaultSort('version','desc');
    }
}
