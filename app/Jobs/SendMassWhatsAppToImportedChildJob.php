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

class SendMassWhatsAppToImportedChildJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $childId,
    ) {}

    public function handle(ChildQrWhatsappService $qrService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $child = Child::find($this->childId);

        if (! $child) {
            Log::warning('MassWhatsApp: nino no encontrado', ['child_id' => $this->childId]);

            return;
        }

        if (! $child->phone || ! $child->phone_lada) {
            Log::warning('MassWhatsApp: nino sin telefono', [
                'child_id' => $child->id,
                'child_code' => $child->code,
            ]);

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

            throw $e;
        }
    }
}
