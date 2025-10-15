<?php

namespace App\Filament\Resources\RagDocumentResource\Pages;

use App\Filament\Resources\RagDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessRagDocument;
use Filament\Forms\Components\Grid;
use App\Domain\Rag\RagDocumentIndexer;

class ManageRagDocuments extends ManageRecords
{
    protected static string $resource = RagDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo documento')
                ->modalHeading('Nuevo documento RAG')
                ->form(function () {
                    $schema = RagDocumentResource::getFormSchema(false);

                    return [
                        Grid::make()
                            ->columns(2)
                            ->schema($schema),
                    ];
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $disk = config('files.documents_disk', 'local');
                    $path = $data['source_path'] ?? null;

                    // default de store si viene vacío
                    if (empty($data['store'])) {
                        $data['store'] = config('pinecone.namespace');
                    }

                    if ($path) {
                        $stream = Storage::disk($disk)->readStream($path);
                        $hash   = hash('sha256', stream_get_contents($stream));
                        if (is_resource($stream)) {
                            fclose($stream);
                        }

                        $data['hash_sha256'] = $hash;
                        $data['mime']        = Storage::disk($disk)->mimeType($path) ?? null;
                        $data['size_bytes']  = Storage::disk($disk)->size($path) ?? 0;
                        $data['status']      = 'pending';
                    } else {
                        // sin archivo no se puede indexar
                        $data['status'] = 'failed';
                    }

                    return $data;
                })
                ->after(function ($record) {
                // En lugar de encolar aquí a mano, delega SIEMPRE al servicio central
                if ($record->is_active) {
                    app(RagDocumentIndexer::class)->handleAfterEdit($record);
                }
            }),
        ];
    }
}
