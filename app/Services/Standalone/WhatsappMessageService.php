<?php

namespace App\Services\Standalone;

use App\Models\Lada;
use App\Models\WhatsappMessage;
use App\Jobs\ProcessWhatsappQueueBatchJob;

class WhatsappMessageService
{
    public function getIndexData(): array
    {
        return [
            'countryCodes' => Lada::options(),
            'selectedCountryCode' => Lada::defaultCode(),
        ];
    }

    public function sendPdf(array $validated): array
    {
        $normalizedPhone = Lada::normalizeLocal(
            (string) $validated['to_phone'],
            (string) $validated['to_country_code']
        );

        if (! $normalizedPhone) {
            return [
                'ok' => false,
                'message' => 'No se pudo validar el número de teléfono.',
                'errors' => [
                    'to_phone' => ['El número de teléfono no es válido.'],
                ],
            ];
        }

        $path = $validated['pdf_file']->store('whatsapp/gafetes');
        $legend = config('baileys.legend');

        $message = WhatsappMessage::create([
            'to_phone' => $normalizedPhone,
            'country_code' => (string) $validated['to_country_code'],
            'message_type' => 'document',
            'message_body' => $validated['caption'] ?? 'Te compartimos tu gafete en PDF.',
            'pdf_path' => $path,
            'filename' => $validated['pdf_file']->getClientOriginalName(),
            'status' => WhatsappMessage::STATUS_PENDING,
            'max_retries' => config('baileys.retry.max_retries'),
            'legend_text' => $legend,
        ]);

        ProcessWhatsappQueueBatchJob::dispatch()->onQueue('whatsapp');

        return [
            'ok' => true,
            'message' => 'PDF agregado a la cola de envíos.',
            'data' => $message->only(['id', 'to_phone', 'status', 'created_at']),
        ];
    }
}
