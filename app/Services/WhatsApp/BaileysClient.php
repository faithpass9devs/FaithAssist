<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappMessage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BaileysClient
{
    public function send(WhatsappMessage $message): array
    {
        if (! config('baileys.enabled')) {
            throw new RuntimeException('El servicio de WhatsApp no está habilitado.');
        }

        $payload = [
            'to' => $message->to_phone,
            'text' => trim($message->message_body."\n\n".$message->legend_text),
        ];

        if ($message->pdf_path) {
            if (! Storage::exists($message->pdf_path)) {
                throw new RuntimeException('No se encontró el archivo PDF para enviar.');
            }

            $payload['document_path'] = Storage::path($message->pdf_path);
            $payload['filename'] = $message->filename ?: 'gafete.pdf';
        }

        $response = $this->request()->post('/send', $payload);

        if (! $response->successful()) {
            throw new RuntimeException((string) ($response->json('message') ?: 'Baileys no pudo enviar el mensaje.'));
        }

        return $response->json();
    }

    public function status(): array
    {
        return $this->request()->get('/status')->json();
    }

    private function request(): PendingRequest
    {
        $baseUrl = (string) config('baileys.base_url');
        $token = (string) config('baileys.internal_token');

        if ($baseUrl === '' || $token === '') {
            throw new RuntimeException('Baileys no está configurado correctamente (base_url / internal_token).');
        }

        return Http::baseUrl($baseUrl)
            ->withToken($token)
            ->acceptJson()
            ->timeout(30);
    }
}
