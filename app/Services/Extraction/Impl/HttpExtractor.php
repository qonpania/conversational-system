<?php

namespace App\Services\Extraction\Impl;

use App\Services\Extraction\Extractor;
use Illuminate\Support\Facades\Http;

class HttpExtractor implements Extractor
{
    public function extract(string $bytes, ?string $mime): string
    {
        $base = rtrim(config('services.embedder.base_url'), '/'); // ✅
        if (empty($base)) {
            throw new \RuntimeException('services.embedder.base_url vacío');
        }

        $url = $base . '/extract'; // ✅ no '/extract' a secas

        $res = Http::timeout(120)
            ->attach('file', $bytes, 'upload')
            ->post($url, ['mime' => $mime])
            ->throw()
            ->json();

        return $res['text'] ?? '';
    }
}
