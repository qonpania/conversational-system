<?php

namespace App\Jobs;

use App\Models\RagDocument;
use App\Services\Embedding\Embedder;
use App\Services\Extraction\Extractor;
use App\Services\Vector\PineconeClient;
use App\Support\Chunker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonException;

class ProcessRagDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function __construct(public string $documentId)
    {
        //
    }

    public function handle(Extractor $extractor, Embedder $embedder, PineconeClient $pinecone): void
    {
        $doc = RagDocument::findOrFail($this->documentId);

        // No procesar si está inactivo (doble seguridad)
        if (! $doc->is_active) {
            Log::info("ProcessRagDocument: documento inactivo, abortando", ['id' => $doc->id]);
            return;
        }

        $doc->update(['status' => 'processing']);

        try {
            $disk = config('files.documents_disk', 'local');

            // Leer el archivo (binario)
            $content = Storage::disk($disk)->get($doc->source_path);

            // Extraer texto con tu extractor (puede devolver string en distinta codificación)
            $rawText = trim($extractor->extract($content, $doc->mime) ?? '');

            // Normalizar/limpiar UTF-8
            $text = $this->cleanUtf8String($rawText);

            if ($text === '') {
                Log::warning("ProcessRagDocument: texto vacío después de extracción/limpieza", ['id' => $doc->id]);
                $doc->update(['status' => 'failed']);
                return;
            }

            // Chunkear (usa tu Chunker)
            $chunks = Chunker::split($text, 700, 100);
            if (empty($chunks)) {
                Log::warning("ProcessRagDocument: chunker devolvió vacío", ['id' => $doc->id]);
                $doc->update(['status' => 'failed']);
                return;
            }

            // Limpiar cada chunk y filtrar basura
            $chunksClean = [];
            foreach ($chunks as $c) {
                $cClean = $this->cleanUtf8String((string) $c);
                // ignora chunks demasiado cortos que no aportan
                if (mb_strlen($cClean) < 3) {
                    continue;
                }
                $chunksClean[] = $cClean;
            }

            if (empty($chunksClean)) {
                Log::warning("ProcessRagDocument: todos los chunks filtrados por longitud", ['id' => $doc->id]);
                $doc->update(['status' => 'failed']);
                return;
            }

            // Obtener embeddings (asegúrate que Embedder devuelve array de vectores)
            $vectors = $embedder->embed($chunksClean);

            if (!is_array($vectors) || count($vectors) !== count($chunksClean)) {
                Log::error("ProcessRagDocument: mismatch vectors vs chunks", [
                    'id' => $doc->id,
                    'chunks' => count($chunksClean),
                    'vectors' => is_array($vectors) ? count($vectors) : gettype($vectors),
                ]);
                $doc->update(['status' => 'failed']);
                return;
            }

            // Namespace: usar store o fallback
            $namespace = $doc->store ?: config('pinecone.namespace');

            // Construir puntos con metadata sanitizada (y texto incluido)
            $points = [];
            foreach ($chunksClean as $i => $chunkText) {
                $rawMeta = [
                    'document_id' => (string) $doc->id,
                    'chunk_index' => $i,
                    'doc_type'    => (string) $doc->doc_type,
                    'version'     => (string) $doc->version,
                    'vigente'     => true,
                    'title'       => (string) $doc->title,
                    'store'       => $namespace,
                    'text'        => $chunkText,
                ];

                if (is_array($doc->extra)) {
                    // merge sin sobreescribir claves anteriores explícitas
                    $rawMeta = array_merge($rawMeta, $doc->extra);
                }

                $meta = $this->sanitizeMeta($rawMeta);

                $points[] = [
                    'id'       => "doc:{$doc->id}:v{$doc->version}:c{$i}",
                    'values'   => $vectors[$i],
                    'metadata' => $meta,
                ];
            }

            // Comprobar que points serialice a JSON (catch temprano)
            try {
                json_encode($points, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                Log::error("ProcessRagDocument: json_encode failed for points", [
                    'id' => $doc->id,
                    'error' => $e->getMessage(),
                ]);
                $doc->update(['status' => 'failed']);
                return;
            }

            // Hacer upsert a Pinecone via el cliente inyectado
            $pinecone->upsertBatch(
                index: config('pinecone.index', 'rag-main'),
                namespace: $namespace,
                points: $points
            );

            // Actualizar estado del documento
            $doc->update([
                'status'       => 'ready',
                'indexed_at'   => now(),
                'vector_count' => count($points),
                'extra'        => array_merge($doc->extra ?? [], [
                    'last_indexed_hash'    => $doc->hash_sha256,
                    'last_indexed_version' => $doc->version,
                ]),
            ]);

            Log::info("ProcessRagDocument: indexado OK", ['id' => $doc->id, 'points' => count($points)]);
            return;
        } catch (\Throwable $e) {
            // Cualquier excepción -> marcar failed y loguear para investigar
            Log::error("ProcessRagDocument: excepción durante indexado", [
                'id' => $doc->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $doc->update(['status' => 'failed']);
            return;
        }
    }

    /**
     * Limpia / normaliza una cadena a UTF-8 válido.
     */
    private function cleanUtf8String(string $s): string
    {
        if ($s === '') {
            return '';
        }

        // Normaliza finales de línea
        $s = str_replace(["\r\n", "\r"], "\n", $s);

        // Si no está ya en UTF-8, intentar convertir automáticamente
        if (!mb_detect_encoding($s, 'UTF-8', true)) {
            // 'auto' intenta detectar
            $converted = @mb_convert_encoding($s, 'UTF-8', 'auto');
            if ($converted !== false) {
                $s = $converted;
            }
        }

        // Elimina bytes inválidos
        $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s);

        // Remueve caracteres de control excepto tab/newline
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $s);

        // Colapsa espacios múltiples
        $s = preg_replace('/[ \t]{2,}/u', ' ', $s);

        // Trim
        $s = trim($s);

        return $s;
    }

    /**
     * Sanitiza metadata para Pinecone:
     * - elimina nulls
     * - convierte arrays a listas de strings
     * - fuerza strings a UTF-8 limpios
     */
    private function sanitizeMeta(array $meta): array
    {
        $out = [];
        foreach ($meta as $k => $v) {
            if ($v === null) {
                // Pinecone no acepta nulls
                continue;
            }

            // primitivas
            if (is_bool($v) || is_int($v) || is_float($v)) {
                $out[$k] = $v;
                continue;
            }

            // strings -> limpiar
            if (is_string($v)) {
                $out[$k] = $this->cleanUtf8String($v);
                continue;
            }

            // arrays -> convertir a lista de strings (filtrando nulls)
            if (is_array($v)) {
                $list = array_values(array_map(
                    fn($x) => $this->cleanUtf8String((string) $x),
                    array_filter($v, fn($x) => $x !== null)
                ));
                $out[$k] = $list;
                continue;
            }

            // cualquier otro tipo -> string limpio
            $out[$k] = $this->cleanUtf8String((string) $v);
        }

        return $out;
    }
}
