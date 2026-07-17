<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WhatsappService
{
    private ?string $token;
    private ?string $phoneNumberId;
    private string $apiVersion;
    private string $baseUrl;

    public function __construct()
    {
        $this->token = config('meta.whatsapp.token');
        $this->phoneNumberId = config('meta.whatsapp.phone_number_id');
        $this->apiVersion = (string) config('meta.whatsapp.api_version', 'v25.0');
        $this->baseUrl = (string) config('meta.whatsapp.base_url', 'https://graph.facebook.com');
    }

    public function uploadPdf(string $storagePath): string
    {
        $this->assertConfigured();

        if (!Storage::exists($storagePath)) {
            throw new RuntimeException('No se encontró el archivo PDF seleccionado. Vuelve a cargarlo e inténtalo de nuevo.');
        }

        $absolutePath = Storage::path($storagePath);

        $response = Http::withToken($this->token)
            ->attach(
                'file',
                file_get_contents($absolutePath),
                basename($absolutePath)
            )
            ->post($this->endpoint("/{$this->phoneNumberId}/media"), [
                'messaging_product' => 'whatsapp',
                'type' => 'application/pdf',
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('No se pudo preparar el PDF para su envío. Intenta de nuevo en unos minutos.');
        }

        return $response->json('id');
    }

    public function uploadImage(string $storagePath): string
    {
        $this->assertConfigured();

        if (!Storage::exists($storagePath)) {
            throw new RuntimeException('No se encontró la imagen seleccionada.');
        }

        $absolutePath = Storage::path($storagePath);

        $response = Http::withToken($this->token)
            ->attach(
                'file',
                file_get_contents($absolutePath),
                basename($absolutePath),
                ['Content-Type' => 'image/png']
            )
            ->post($this->endpoint("/{$this->phoneNumberId}/media"), [
                'messaging_product' => 'whatsapp',
                'type' => 'image/png',
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('No se pudo subir la imagen a WhatsApp.');
        }

        return $response->json('id');
    }

    public function sendImage(
        string $toPhone,
        string $mediaId,
        ?string $caption = null
    ): array {
        $this->assertConfigured();

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($toPhone),
            'type' => 'image',
            'image' => [
                'id' => $mediaId,
            ],
        ];

        if ($caption) {
            $payload['image']['caption'] = $caption;
        }

        $response = Http::withToken($this->token)
            ->asJson()
            ->post($this->endpoint("/{$this->phoneNumberId}/messages"), $payload);

        if (!$response->successful()) {
            throw new RuntimeException($this->friendlyMessageFromResponse($response->body()));
        }

        return [
            'payload' => $payload,
            'response' => $response->json(),
        ];
    }

    public function uploadAndSendImage(
        string $toPhone,
        string $storagePath,
        ?string $caption = null
    ): array {
        $mediaId = $this->uploadImage($storagePath);

        $sendResult = $this->sendImage(
            toPhone: $toPhone,
            mediaId: $mediaId,
            caption: $caption
        );

        return [
            'media_id' => $mediaId,
            'payload' => $sendResult['payload'],
            'response' => $sendResult['response'],
        ];
    }

    public function sendPdfDocument(
        string $toPhone,
        string $mediaId,
        string $filename = 'gafete.pdf',
        ?string $caption = null
    ): array {
        $this->assertConfigured();

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($toPhone),
            'type' => 'document',
            'document' => [
                'id' => $mediaId,
                'filename' => $filename,
            ],
        ];

        if ($caption) {
            $payload['document']['caption'] = $caption;
        }

        $response = Http::withToken($this->token)
            ->asJson()
            ->post($this->endpoint("/{$this->phoneNumberId}/messages"), $payload);

        if (!$response->successful()) {
            throw new RuntimeException($this->friendlyMessageFromResponse($response->body()));
        }

        return [
            'payload' => $payload,
            'response' => $response->json(),
        ];
    }

    public function uploadAndSendPdf(
        string $toPhone,
        string $storagePath,
        string $filename = 'gafete.pdf',
        ?string $caption = null
    ): array {
        $mediaId = $this->uploadPdf($storagePath);

        $sendResult = $this->sendPdfDocument(
            toPhone: $toPhone,
            mediaId: $mediaId,
            filename: $filename,
            caption: $caption
        );

        return [
            'media_id' => $mediaId,
            'payload' => $sendResult['payload'],
            'response' => $sendResult['response'],
        ];
    }

    private function assertConfigured(): void
    {
        if (! $this->token || ! $this->phoneNumberId) {
            throw new RuntimeException('El servicio de mensajería no está disponible en este momento. Intenta de nuevo más tarde.');
        }
    }

    private function endpoint(string $path): string
    {
        return "{$this->baseUrl}/{$this->apiVersion}{$path}";
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone);
    }

    private function friendlyMessageFromResponse(string $responseBody): string
    {
        $decoded = json_decode($responseBody, true);
        $error = is_array($decoded) ? ($decoded['error'] ?? []) : [];
        $code = is_array($error) ? ($error['code'] ?? null) : null;
        $message = is_array($error) ? (string) ($error['message'] ?? '') : '';
        $details = is_array($error) ? (string) ($error['error_data']['details'] ?? '') : '';

        if (
            $code === 131030
            || str_contains($message, 'Recipient phone number not in allowed list')
            || str_contains($details, 'no está en la lista de autorizados')
        ) {
            return 'No se pudo enviar el PDF. El número ingresado no está autorizado para recibir mensajes. Verifica el número.';
        }

        return 'No se pudo enviar el PDF por WhatsApp. Intenta de nuevo en unos minutos.';
    }
}
