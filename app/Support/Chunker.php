<?php
namespace App\Support;

class Chunker {
  /**
   * @return array<int, string>
   */
  public static function split(string $text, int $tokens = 700, int $overlap = 100): array {
    // Heurística simple por caracteres (~4 chars/token de aproximación)
    $max = $tokens * 4;
    $ov  = $overlap * 4;
    $text = trim(preg_replace('/\s+/', ' ', $text));
    $chunks = [];
    $i = 0;
    while ($i < strlen($text)) {
      $end = min($i + $max, strlen($text));
      $slice = substr($text, $i, $end - $i);
      $chunks[] = $slice;
      if ($end >= strlen($text)) break;
      $i = $end - $ov;
      if ($i < 0) $i = 0;
    }
    return $chunks;
  }
}
