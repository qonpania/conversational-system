<?php

namespace App\Services\Vector\Impl;

use App\Services\Vector\PineconeClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class PineconeHttpClient implements PineconeClient
{
    /** Cliente HTTP con header Api-Key y timeout sensato */
    private function client()
    {
        return Http::withHeaders([
            'Api-Key'       => config('pinecone.key'),
            'Content-Type'  => 'application/json',
        ])->timeout(30);
    }

    /**
     * Upsert en el host del índice:
     *   POST {INDEX_HOST}/vectors/upsert
     *  - $points: [['id'=>..., 'values'=>[...], 'metadata'=>[...]], ...]
     */
    public function upsertBatch(string $index, string $namespace, array $points): void
    {
        $base = rtrim(config('pinecone.base_url'), '/'); // host del índice
        $url  = "{$base}/vectors/upsert";

        // (Opcional) chunking por si envías lotes grandes
        $chunks = array_chunk($points, 100); // ajusta si necesitas
        foreach ($chunks as $batch) {
            $payload = [
                'namespace' => $namespace,
                'vectors'   => array_values($batch),
            ];

            $this->client()->post($url, $payload)->throw();
        }
    }

    /**
     * Borrado por document_id usando filtro:
     *   POST {INDEX_HOST}/vectors/delete
     *  - Filtro debe usar '$eq' (sin backslash)
     */
    public function deleteByDocument(string $index, string $namespace, string $documentId): void
    {
        $base = rtrim(config('pinecone.base_url'), '/');
        $url  = "{$base}/vectors/delete";

        $payload = [
            'namespace' => $namespace,
            'filter'    => [
                'document_id' => ['$eq' => (string) $documentId],
            ],
        ];

        $this->client()->post($url, $payload)->throw();
    }
}
