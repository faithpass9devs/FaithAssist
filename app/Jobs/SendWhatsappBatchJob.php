<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\WhatsApp\BaileysClient;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsappBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        /** @var int[] IDs de mensajes pendientes */
        public readonly array $messageIds,
    ) {}

    public function handle(BaileysClient $client): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $messages = WhatsappMessage::query()
            ->whereIn('id', $this->messageIds)
            ->where('status', 'pending')
            ->get();

        if ($messages->isEmpty()) {
            return;
        }

        $items = $messages->map(fn (WhatsappMessage $message) => [
            'message' => $message,
            'caption' => trim($message->message_body."\n\n".$message->legend_text),
        ])->all();

        $result = $client->sendBatch($items);

        $sentCount = 0;
        $failedCount = 0;

        foreach ($result['results'] as $index => $item) {
            $message = $messages[$index] ?? null;

            if (! $message) {
                continue;
            }

            if ($item['success']) {
                $message->update([
                    'status' => 'sent',
                    'baileys_message_id' => $item['message_id'] ?? null,
                    'sent_at' => now(),
                ]);
                $sentCount++;
            } else {
                $message->update([
                    'status' => 'failed',
                    'error_message' => $item['error'] ?? 'Error desconocido',
                ]);
                $failedCount++;
            }
        }

        Log::info('Lote de WhatsApp enviado', [
            'batch_job_id' => $this->job?->getJobId(),
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total' => $messages->count(),
        ]);
    }
}
