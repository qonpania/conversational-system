<?php

namespace App\Services\Embedding;

interface Embedder {
  /** @return array<array<float>> vectors aligned to chunks order */
  public function embed(array $chunks): array;
}
