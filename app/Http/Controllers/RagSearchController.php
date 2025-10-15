<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Embedding\Embedder;
use App\Services\Vector\PineconeQueryClient;

class RagSearchController extends Controller
{
    public function search(Request $r, Embedder $embedder, PineconeQueryClient $pc)
    {
        $data = $r->validate([
            'query'        => 'required|string|max:500',
            'store'        => 'nullable|string|max:64',
            'doc_type'     => 'nullable|array',
            'doc_type.*'   => 'string',
            'topK'         => 'nullable|integer|min:1|max:50',
        ]);

        $embedding = $embedder->embed([$data['query']])[0];

        $ns = $data['store'] ?? config('pinecone.namespace');

        // ✅ SIN backslash en las claves:
        $filter = ['vigente' => ['$eq' => true]];

        $filter['store'] = ['$eq' => $ns];

        if (!empty($data['doc_type'])) {
            $filter['doc_type'] = ['$in' => $data['doc_type']];
        }

        $res = $pc->query(
            index: config('pinecone.index', 'rag-main'),
            namespace: $ns,
            embedding: $embedding,
            filter: $filter,
            topK: $data['topK'] ?? 24,
        );

        return response()->json($res);
    }
}
