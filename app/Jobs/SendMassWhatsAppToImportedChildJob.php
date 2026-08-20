<?php

namespace App\Jobs;

use App\Models\Catechism\Child;
use App\Models\FailedWhatsappChild;
use App\Services\Catechism\ChildQrWhatsappService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMassWhatsAppToImportedChildJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $childId,
        public readonly ?int $batchId = null,
    ) {}

    public function handle(ChildQrWhatsappService $qrService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $child = Child::find($this->childId);

        if (! $child) {
            $this->recordFailure('Nino no encontrado en la base de datos');

            return;
        }

        if (! $child->phone || ! $child->phone_lada) {
            $this->recordFailure('El nino no tiene telefono registrado');

            return;
        }

        try {
            $qrService->sendChildQrBadge($child);

            Log::info('MassWhatsApp: gafete enviado a cola', [
                'child_id' => $child->id,
                'child_code' => $child->code,
            ]);
        } catch (\Throwable $e) {
            Log::error('MassWhatsApp: error enviando gafete', [
                'child_id' => $child->id,
                'child_code' => $child->code,
                'error' => $e->getMessage(),
            ]);

            $this->recordFailure($e->getMessage());

            throw $e;
        }
    }

    private function recordFailure(string $message): void
    {
        $child = Child::withTrashed()->find($this->childId);

        FailedWhatsappChild::create([
            'child_id' => $this->childId,
            'batch_id' => $this->batchId,
            'error_message' => $message.' | '.$child?->full_name.' ('.$child?->code.')',
        ]);
    }
}
