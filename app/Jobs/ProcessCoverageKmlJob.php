<?php

namespace App\Jobs;

use App\Services\Coverage\ImportCoverageKmlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessCoverageKmlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $relativePath,
        public bool $replaceExisting = false,
        public ?string $notes = null,
        public ?int $userId = null,
    ) {}

    public function handle(ImportCoverageKmlService $service): void
    {
        $fullPath = Storage::disk('local')->path($this->relativePath);

        if (! file_exists($fullPath)) {
            Log::warning('[KML] Archivo no encontrado en job', [
                'path'    => $fullPath,
                'user_id' => $this->userId,
            ]);
            return;
        }

        try {
            $result = $service->handle(
                absolutePath: $fullPath,
                replaceExisting: $this->replaceExisting,
                notes: $this->notes,
            );

            Log::info('[KML] Importación KML completada desde job', [
                'path'    => $fullPath,
                'user_id' => $this->userId,
                'result'  => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('[KML] Error procesando KML en job', [
                'path'      => $fullPath,
                'user_id'   => $this->userId,
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
        }
    }
}
