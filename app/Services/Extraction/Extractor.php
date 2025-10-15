<?php

namespace App\Services\Extraction;

interface Extractor {
  public function extract(string $bytes, ?string $mime): string;
}
