<?php

namespace App\Domain\Rag;

use App\Jobs\ProcessRagDocument;
use App\Models\RagDocument;
use App\Services\Vector\PineconeClient;
use Illuminate\Support\Arr;

class RagDocumentIndexer
{
    public function __construct(
        protected PineconeClient $pinecone
    ) {}

    public function disable(RagDocument $doc): void
    {
        // Marcar inactivo primero
        $doc->update(['is_active' => false]);

        // Borrar vectores
        $ns = $this->namespaceOf($doc);
        $this->pinecone->deleteByDocument(
            index: config('pinecone.index'),
            namespace: $ns,
            documentId: (string) $doc->id
        );

        // Limpiar metadatos de indexación
        $extra = $doc->extra ?? [];
        unset($extra['last_indexed_hash'], $extra['last_indexed_version']);

        $doc->update([
            'status'       => 'disabled',
            'vector_count' => 0,
            'indexed_at'   => null,
            'extra'        => $extra,
        ]);
    }

    public function enable(RagDocument $doc): void
    {
        if (! $doc->is_active) {
            $doc->update(['is_active' => true]);
        }

        // Si venía de disabled o no tiene vectores / no está ready => reindex
        $needsIndex = $doc->status === 'disabled'
            || $doc->vector_count == 0
            || $doc->status !== 'ready';

        $changed = $this->contentChanged($doc);

        if ($changed) {
            // hard: limpiamos y reindexamos
            $this->hardReindex($doc);
            return;
        }

        if ($needsIndex) {
            $this->softReindex($doc);
        }
    }

    public function hardReindex(RagDocument $doc): void
    {
        $ns = $this->namespaceOf($doc);
        $this->pinecone->deleteByDocument(
            index: config('pinecone.index'),
            namespace: $ns,
            documentId: (string) $doc->id
        );
        $doc->update(['vector_count' => 0, 'indexed_at' => null]);
        $this->softReindex($doc);
    }

    public function softReindex(RagDocument $doc): void
    {
        $doc->update(['status' => 'pending']);
        ProcessRagDocument::dispatch($doc->id)->afterCommit();
    }

    public function delete(RagDocument $doc): void
    {
        $ns = $this->namespaceOf($doc);
        $this->pinecone->deleteByDocument(
            index: config('pinecone.index'),
            namespace: $ns,
            documentId: (string) $doc->id
        );
    }

    /**
     * Lógica central para "qué hacer después de editar".
     * Decide basado en cambios del modelo (post-save).
     */
    public function handleAfterEdit(RagDocument $doc): void
    {
        // Si cambió is_active, priorizamos transición de estado
        if ($doc->wasChanged('is_active')) {
            if ($doc->is_active) {
                $this->enable($doc);
            } else {
                $this->disable($doc);
            }
            return;
        }

        // Si no cambió is_active, vemos si cambió contenido o versión (hard)
        if ($this->contentChanged($doc) || $doc->wasChanged(['source_path', 'version', 'mime', 'size_bytes'])) {
            $this->hardReindex($doc);
            return;
        }

        // Si quedó inconsistente (sin vectores o no ready) y está activo => reindex suave
        if ($doc->is_active && ($doc->vector_count == 0 || $doc->status !== 'ready')) {
            $this->softReindex($doc);
        }
    }

    private function contentChanged(RagDocument $doc): bool
    {
        $lastHash = data_get($doc->extra, 'last_indexed_hash');
        $lastVer  = data_get($doc->extra, 'last_indexed_version');

        return ($lastHash && $lastHash !== $doc->hash_sha256)
            || ($lastVer && $lastVer !== $doc->version);
    }

    private function namespaceOf(RagDocument $doc): string
    {
        return $doc->store ?: (string) config('pinecone.namespace');
    }
}
