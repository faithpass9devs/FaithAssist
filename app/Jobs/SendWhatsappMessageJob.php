<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\WhatsApp\BaileysClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $messageId) {}

    public function handle(BaileysClient $client): void
    {
        $message = WhatsappMessage::query()->find($this->messageId);

        if (! $message || $message->status === WhatsappMessage::STATUS_SENT) {
            return;
        }

        try {
            $result = $client->send($message);

            $message->update([
                'status' => WhatsappMessage::STATUS_SENT,
                'baileys_message_id' => $result['message_id'] ?? null,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $retryCount = $message->retry_count + 1;
            Log::error('Error enviando mensaje de WhatsApp por Baileys', [
                'message_id' => $message->id,
                'to_phone' => $message->to_phone,
                'retry_count' => $retryCount,
                'error' => $exception->getMessage(),
            ]);
            $message->update([
                'retry_count' => $retryCount,
                'status' => $retryCount >= $message->max_retries
                    ? WhatsappMessage::STATUS_FAILED
                    : WhatsappMessage::STATUS_PENDING,
                'error_message' => $exception->getMessage(),
            ]);

            if ($retryCount < $message->max_retries) {
                self::dispatch($message->id)
                    ->delay(now()->addSeconds(config('baileys.retry.delay')))
                    ->onQueue('whatsapp');
            }
        } finally {
            if ($message?->pdf_path && $message->fresh()?->status === WhatsappMessage::STATUS_SENT) {
                Storage::delete($message->pdf_path);
            }
        }
    }
}
