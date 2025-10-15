<?php
namespace App\Services\Embedding\Impl;

use App\Services\Embedding\Embedder;
use Illuminate\Support\Facades\Http;

class HttpEmbedder implements Embedder {
  public function embed(array $chunks): array {
    $res = Http::timeout(120)
      ->post(rtrim(config('services.embedder.base_url'),'/').'/embed', [
        'texts' => array_values($chunks),
      ])->throw()->json();
    return $res['vectors'] ?? [];
  }
}
