<?php
namespace App\Services\Vector;

interface PineconeQueryClient {
  public function query(string $index, string $namespace, array $embedding, array $filter = [], int $topK = 24): array;
}
