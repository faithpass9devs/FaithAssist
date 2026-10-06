<?php

namespace App\Jobs;

use App\Exports\Catechism\ChildImportErrorsExport;
use App\Models\Catechism\ChildImportBatch;
use App\Models\Catechism\ChildImportError;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class FinalizeChildImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly string $importBatchId)
    {
    }

    public function handle(): void
    {
        $batch = ChildImportBatch::query()->where('batch_id', $this->importBatchId)->first();

        if (! $batch) {
            return;
        }

        $errors = $batch->errors()
            ->orderBy('line')
            ->get(['line', 'message'])
            ->map(fn (ChildImportError $error): array => [
                'line' => $error->line,
                'message' => (string) $error->message,
            ])
            ->all();

        $batch->forceFill(['failed_count' => count($errors) ?: $batch->failed_count])->save();

        if ($errors === []) {
            return;
        }

        $path = "imports/{$this->importBatchId}/errores.xlsx";

        try {
            Excel::store(new ChildImportErrorsExport($errors), $path, 'local', ExcelWriter::XLSX);

            $batch->forceFill(['error_report_path' => $path])->save();
            $batch->errors()->delete();
        } catch (Throwable $e) {
            // Se conservan las filas de error para poder diagnosticarlas.
            Log::error('Importación niños: no se pudo generar el reporte de errores', [
                'batch_id' => $this->importBatchId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
