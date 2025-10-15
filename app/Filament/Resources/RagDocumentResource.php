<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RagDocumentResource\Pages;
use App\Filament\Resources\RagDocumentResource\RelationManagers;
use App\Models\RagDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Services\Vector\PineconeClient;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessRagDocument;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Actions\ActionGroup;
use App\Domain\Rag\RagDocumentIndexer;


class RagDocumentResource extends Resource
{
    protected static ?string $model = RagDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'RAG Documentos';
    protected static ?string $slug = 'rag-documents';

    public static function getFormSchema(bool $isEdit = false): array
    {
        return [
                Forms\Components\TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->maxLength(150),

                Forms\Components\FileUpload::make('source_path')
                    ->label($isEdit ? 'Reemplazar archivo (opcional)' : 'Archivo')
                    ->disk(config('files.documents_disk','local'))
                    ->directory('documents')
                    ->preserveFilenames()
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'text/plain',
                    ])
                    ->required(!$isEdit)
                    ->dehydrated(fn ($state) => filled($state)),

                Forms\Components\Select::make('doc_type')
                    ->label('Tipo')
                    ->native(false)
                    ->options([
                        'sop' => 'SOP',
                        'faq' => 'FAQ',
                        'producto' => 'Producto',
                        'plantilla' => 'Plantilla',
                        'ticket' => 'Ticket',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('store')
                    ->label('Tienda / Tenant')
                    ->maxLength(64),
                Forms\Components\TextInput::make('version')
                    ->default(fn() => now()->format('Y-m'))
                    ->helperText('Usa versión para invalidar contenido viejo.')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
                Forms\Components\KeyValue::make('extra')
                    ->label('Metadatos (opcional)')
                    ->addButtonLabel('Agregar'),
            ];
    }


    public static function form(Form $form): Form
    {
        return $form->schema(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll(fn () =>
                RagDocument::whereIn('status', ['processing', 'pending'])->exists()
                    ? '3s'
                    : null
                )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\BadgeColumn::make('doc_type')
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('store')
                    ->label('Tienda')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('version')
                    ->label('Versión'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Activo'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Estado'),
                Tables\Columns\TextColumn::make('vector_count')
                    ->label('Vectores'),
                Tables\Columns\TextColumn::make('indexed_at')
                    ->dateTime()
                    ->since()
                    ->label('Indexado'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->label('Actualizado'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('doc_type')
                    ->label('Tipo')
                    ->native(false)
                    ->options([
                        'sop'=>'SOP',
                        'faq'=>'FAQ',
                        'producto'=>'Producto',
                        'plantilla'=>'Plantilla',
                        'ticket'=>'Ticket',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->native(false)
                    ->label('Solo activos'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->native(false)
                    ->options([
                        'pending'=>'pending',
                        'processing'=>'processing',
                        'ready'=>'ready',
                        'failed'=>'failed',
                    ]),
            ])

            ->actions([
                ActionGroup::make([
                    // EDITAR (recalcula metadatos si cambió el file)
                    Tables\Actions\EditAction::make()
                        ->label('Editar')
                        ->modalHeading('Editar documento RAG')
                        ->modalWidth('2xl')
                        ->form(fn () => self::getFormSchema(true))
                        ->mutateFormDataUsing(function (array $data, Model $record): array {
                            $disk = config('files.documents_disk', 'local');
                            $path = $data['source_path'] ?? null;

                            if ($path && $path !== $record->source_path) {
                                $stream = Storage::disk($disk)->readStream($path);
                                $hash   = hash('sha256', stream_get_contents($stream));
                                if (is_resource($stream)) fclose($stream);

                                $data['hash_sha256'] = $hash;
                                $data['mime']        = Storage::disk($disk)->mimeType($path) ?? null;
                                $data['size_bytes']  = Storage::disk($disk)->size($path) ?? 0;

                                // No marcamos aquí pending: lo decidirá el servicio (hard/soft/no-op)
                            }

                            if (empty($data['store'])) {
                                $data['store'] = $record->store ?: config('pinecone.namespace');
                            }

                            return $data;
                        })
                        ->after(function (Model $record, array $data) {
                            app(RagDocumentIndexer::class)->handleAfterEdit($record);
                        }),


                    // REINDEXAR (admin): con opción HARD
                    Tables\Actions\Action::make('reindex')
                        ->label('Reindexar')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn (RagDocument $r) => $r->is_active)
                        ->form([
                            Forms\Components\Toggle::make('hard')
                                ->label('Hard (borrar en Pinecone antes)')
                                ->default(false),
                        ])
                        ->requiresConfirmation()
                        ->action(function (RagDocument $doc, array $data) {
                            $svc = app(\App\Domain\Rag\RagDocumentIndexer::class);
                            if (!empty($data['hard'])) {
                                $svc->hardReindex($doc);
                            } else {
                                $svc->softReindex($doc);
                            }
                        }),


                        // DESACTIVAR (borra Pinecone y marca disabled)
                    Tables\Actions\Action::make('disable')
                        ->label('Desactivar')
                        ->color('warning')
                        ->visible(fn (RagDocument $r) => $r->is_active)
                        ->requiresConfirmation()
                        ->action(fn (RagDocument $r) => app(RagDocumentIndexer::class)->disable($r)),

                    Tables\Actions\Action::make('enable')
                        ->label('Activar')
                        ->color('success')
                        ->visible(fn (RagDocument $r) => ! $r->is_active)
                        ->action(fn (RagDocument $r) => app(RagDocumentIndexer::class)->enable($r)),

                    // BORRAR (limpia Pinecone y el archivo)
                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation()
                        ->before(function (RagDocument $r) {
                            app(RagDocumentIndexer::class)->delete($r);
                        })
                        ->after(function (RagDocument $r) {
                            // opcional: también borrar archivo local, si quieres mantenerlo aquí
                            Storage::disk(config('files.documents_disk','local'))->delete($r->source_path);
                        }),
                ])
            ])

            ->bulkActions([
                Tables\Actions\BulkAction::make('reindex_selected')
                    ->label('Reindexar seleccionados')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn ($records) => collect($records)->each(
                        fn (RagDocument $r) => ProcessRagDocument::dispatch($r->id)
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRagDocuments::route('/'),
        ];
    }
}
