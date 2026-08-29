<?php

namespace App\Jobs;

use App\Models\Catechism\Child;
use App\Services\Catechism\ChildQrWhatsappService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExportChildPdfJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public readonly int $childId,
        public readonly string $exportBatchId,
    ) {}

    public function handle(ChildQrWhatsappService $qrService): void
    {
        $child = Child::find($this->childId);

        if (! $child) {
            return;
        }

        try {
            $pdfContent = $qrService->generateChildBadgePdf($child);

            if (! is_string($pdfContent) || strlen($pdfContent) < 100) {
                Log::warning('Export PDF: contenido vacío para niño', [
                    'child_id' => $this->childId,
                    'batch_id' => $this->exportBatchId,
                ]);

                return;
            }

            $path = 'exports/'.$this->exportBatchId.'/'.$this->childId.'.pdf';
            Storage::put($path, $pdfContent);

            $child->withoutEvents(function () use ($child): void {
                if (empty($child->badge_pdf_downloaded_at)) {
                    $child->forceFill(['badge_pdf_downloaded_at' => now()])->saveQuietly();
                }
            });
        } catch (Throwable $e) {
            Log::error('Export PDF: error generando gafete', [
                'child_id' => $this->childId,
                'batch_id' => $this->exportBatchId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
