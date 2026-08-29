<?php

namespace App\Jobs;

use App\Models\Catechism\ChildPdfExportBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Throwable;

class CombineChildPdfsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public readonly string $batchId,
        public readonly int $failedCount = 0,
    ) {}

    public function handle(): void
    {
        $batch = ChildPdfExportBatch::query()->where('batch_id', $this->batchId)->first();

        if (! $batch) {
            return;
        }

        $batch->update(['failed_count' => $this->failedCount]);

        $directory = 'exports/'.$this->batchId;
        $childFiles = collect(Storage::allFiles($directory))
            ->filter(fn (string $path): bool => str_ends_with($path, '.pdf'));

        if ($childFiles->isEmpty()) {
            Log::warning('Export PDF: no hay gafetes para combinar', ['batch_id' => $this->batchId]);
            $this->cleanup($directory);

            return;
        }

        try {
            $pdf = new Fpdi;

            foreach ($childFiles->sort() as $path) {
                $absolutePath = Storage::path($path);
                $pageCount = $pdf->setSourceFile($absolutePath);

                for ($i = 1; $i <= $pageCount; $i++) {
                    $pdf->AddPage();
                    $template = $pdf->importPage($i);
                    $pdf->useTemplate($template);
                }
            }

            $resultPath = 'exports/'.$this->batchId.'/'.'gafetes_combinados.pdf';
            Storage::put($resultPath, $pdf->Output('S'));

            $batch->update(['result_storage_path' => $resultPath]);

            $this->cleanupChildFiles($childFiles);

            Log::info('Export PDF: gafetes combinados', [
                'batch_id' => $this->batchId,
                'files' => $childFiles->count(),
            ]);
        } catch (Throwable $e) {
            Log::error('Export PDF: error al combinar gafetes', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function cleanupChildFiles($childFiles): void
    {
        foreach ($childFiles as $path) {
            if (Storage::exists($path)) {
                Storage::delete($path);
            }
        }
    }

    private function cleanup(string $directory): void
    {
        if (Storage::exists($directory)) {
            Storage::deleteDirectory($directory);
        }
    }
}
