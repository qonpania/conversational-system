<?php

namespace App\Services\Vector\Impl;

use App\Services\Vector\PineconeQueryClient;
use Illuminate\Support\Facades\Http;

class PineconeQueryHttpClient implements PineconeQueryClient
{
    private function client()
    {
        return Http::withHeaders([
            'Api-Key'      => config('pinecone.key'),
            'Content-Type' => 'application/json',
        ])->timeout(30);
    }

    /**
     * Consulta:
     *   POST {INDEX_HOST}/query
     *  - $filter DEBE usar claves '$eq', '$in', etc. (sin backslash)
     */
    public function query(string $index, string $namespace, array $embedding, array $filter = [], int $topK = 24): array
    {
        $base = rtrim(config('pinecone.base_url'), '/'); // host del índice
        $url  = "{$base}/query";

        $payload = [
            'namespace'       => $namespace,
            'vector'          => $embedding,
            'topK'            => $topK,
            'includeValues'   => false,
            'includeMetadata' => true,
        ];

        if (!empty($filter)) {
            $payload['filter'] = $filter; // e.g. ['store'=>['$eq'=>'default']]
        }

        return $this->client()->post($url, $payload)->throw()->json();
    }
}
