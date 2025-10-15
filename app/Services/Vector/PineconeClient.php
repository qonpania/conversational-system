<?php
namespace App\Services\Vector;

interface PineconeClient {
  public function upsertBatch(string $index, string $namespace, array $points): void;
  public function deleteByDocument(string $index, string $namespace, string $documentId): void;
}
