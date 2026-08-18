<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;

class ProcessWhatsappQueueBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function middleware(): array
    {
        return [new WithoutOverlapping('baileys-whatsapp-batch')];
    }

    public function handle(): void
    {
        $dailyLimit = config('baileys.queue.daily_limit');
        $sentToday = WhatsappMessage::query()
            ->where('status', WhatsappMessage::STATUS_SENT)
            ->whereDate('sent_at', today())
            ->count();
        $remaining = max(0, $dailyLimit - $sentToday);

        if ($remaining === 0) {
            return;
        }

        $messages = WhatsappMessage::query()
            ->where('status', WhatsappMessage::STATUS_PENDING)
            ->whereColumn('retry_count', '<', 'max_retries')
            ->where(function ($query): void {
                $query->whereNull('batch_key')
                    ->orWhere('updated_at', '<', now()->subMinutes(10));
            })
            ->oldest()
            ->limit(min(config('baileys.queue.batch_size'), $remaining))
            ->get();

        $batchKey = (string) Str::uuid();
        $messages->each(fn (WhatsappMessage $message) => $message->update(['batch_key' => $batchKey]));

        foreach ($messages as $index => $message) {
            SendWhatsappMessageJob::dispatch($message->id)
                ->delay(now()->addSeconds($index * config('baileys.queue.pause_between_messages')))
                ->onQueue('whatsapp');
        }

        if ($messages->isNotEmpty()) {
            self::dispatch()
                ->delay(now()->addSeconds(
                    ($messages->count() * config('baileys.queue.pause_between_messages'))
                    + config('baileys.queue.pause_between_batches')
                ))
                ->onQueue('whatsapp');
        }
    }
}
