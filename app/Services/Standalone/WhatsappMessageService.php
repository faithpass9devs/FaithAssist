<?php

namespace App\Services\Standalone;

use App\Models\Lada;
use App\Models\WhatsappMessage;
use App\Services\WhatsappService;
use Throwable;

class WhatsappMessageService
{
    public function __construct(private readonly WhatsappService $whatsappService) {}

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

        $message = WhatsappMessage::create([
            'to_phone' => $normalizedPhone,
            'country_code' => (string) $validated['to_country_code'],
            'message_type' => 'document',
            'pdf_path' => $path,
            'status' => 'pending',
        ]);

        try {
            $result = $this->whatsappService->uploadAndSendPdf(
                toPhone: $normalizedPhone,
                storagePath: $path,
                filename: $validated['pdf_file']->getClientOriginalName(),
                caption: $validated['caption'] ?? 'Te compartimos el gafete en PDF.'
            );

            $metaMessageId = $result['response']['messages'][0]['id'] ?? null;

            $message->update([
                'media_id' => $result['media_id'],
                'meta_message_id' => $metaMessageId,
                'status' => 'sent',
                'request_payload' => $result['payload'],
                'response_payload' => $result['response'],
            ]);

            return [
                'ok' => true,
                'message' => 'PDF enviado correctamente por WhatsApp.',
                'data' => $message->only(['id', 'to_phone', 'status', 'created_at']),
            ];
        } catch (Throwable $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            report($e);

            $userMessage = 'No se pudo enviar el PDF por WhatsApp.';

            if (
                str_contains($e->getMessage(), 'Recipient phone number not in allowed list')
                || str_contains($e->getMessage(), '131030')
            ) {
                $userMessage = 'No se pudo enviar el PDF. El número ingresado no está autorizado para recibir mensajes. Verifica el número.';
            }

            return [
                'ok' => false,
                'message' => $userMessage,
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ];
        }
    }
}
