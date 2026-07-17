<?php

namespace App\Services\Catechism;

use App\Models\Catechism\Child;
use App\Models\WhatsappMessage;
use App\Services\WhatsappService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ChildQrWhatsappService
{
    public function __construct(
        private readonly WhatsappService $whatsappService
    ) {}

    /**
     * Envía el gafete (QR + datos del niño) por WhatsApp al teléfono registrado
     */
    public function sendChildQrBadge(Child $child): void
    {
        try {
            if (! $this->isConfigured()) {
                Log::info('WhatsApp no está configurado, omitiendo envío de gafete para niño', [
                    'child_id' => $child->id,
                    'child_code' => $child->code,
                ]);

                return;
            }

            $phoneNumber = $this->getNormalizedPhone($child);

            if (! $phoneNumber) {
                Log::warning('Teléfono del niño no disponible, omitiendo envío de gafete', [
                    'child_id' => $child->id,
                    'child_code' => $child->code,
                ]);

                return;
            }

            $qrImagePath = $this->generateQrImage($child->code);

            if (! $qrImagePath) {
                Log::warning('No se pudo generar QR del gafete, omitiendo envío', [
                    'child_id' => $child->id,
                    'child_code' => $child->code,
                ]);

                return;
            }

            $this->sendViaWhatsapp($child, $phoneNumber, $qrImagePath);

            Log::info('Gafete enviado exitosamente por WhatsApp', [
                'child_id' => $child->id,
                'child_code' => $child->code,
                'phone' => $this->maskPhone($phoneNumber),
            ]);
        } catch (Throwable $e) {
            Log::error('Error al enviar gafete por WhatsApp', [
                'child_id' => $child->id ?? null,
                'child_code' => $child->code ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Genera el PDF del gafete con QR y datos del niño
     */
    private function generateQrPdf(Child $child): ?string
    {
        try {
            $qrImagePath = $this->generateQrImage($child->code);

            if (! $qrImagePath) {
                return null;
            }

            $child->load(['church:id,name', 'community:id,name']);

            $html = $this->buildPdfHtml($child, $qrImagePath);

            $filename = "gafete_{$child->code}.pdf";
            $pdfPath = "whatsapp/gafetes/{$filename}";

            // Usar exec con wkhtmltopdf si está disponible, sino usar HTML simple
            $pdfContent = $this->generatePdfContent($html);

            // Validar que el PDF tiene contenido
            if (!$pdfContent || strlen($pdfContent) < 100) {
                Log::error('PDF generado vacío o inválido', [
                    'child_id' => $child->id,
                    'size' => strlen($pdfContent ?? ''),
                ]);
                return null;
            }

            Storage::put($pdfPath, $pdfContent);

            Log::debug('PDF del gafete generado', [
                'child_id' => $child->id,
                'path' => $pdfPath,
                'size' => strlen($pdfContent),
            ]);

            // Limpiar imagen temporal
            if (Storage::exists($qrImagePath)) {
                Storage::delete($qrImagePath);
            }

            return $pdfPath;
        } catch (Exception $e) {
            Log::error('Error generando PDF del gafete', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Genera la imagen QR y la almacena temporalmente
     */
    private function generateQrImage(string $childCode): ?string
    {
        try {
            // Construir URL del QR desde el código del niño
            $qrContent = $childCode;

            // Usar API gratuita de QR code (sin dependencias)
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=256x256&data={$qrContent}";

            $response = Http::timeout(10)->get($qrUrl);

            if (! $response->successful()) {
                return null;
            }

            $filename = "qr_{$childCode}_" . time() . '.png';
            $path = "whatsapp/temp_qr/{$filename}";

            Storage::put($path, $response->body());

            return $path;
        } catch (Exception $e) {
            Log::error('Error generando QR', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Construye el HTML del PDF con QR y datos del niño
     */
    private function buildPdfHtml(Child $child, string $qrImagePath): string
    {
        // Usar ruta absoluta con file:// para que funcione en cualquier contexto
        $qrAbsolutePath = Storage::path($qrImagePath);
        $qrUrl = file_exists($qrAbsolutePath) ? 'file://' . realpath($qrAbsolutePath) : '';

        $churchName = $child->church?->name ?? 'No especificada';
        $communityName = $child->community?->name ?? 'No especificada';

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    text-align: center;
                }
                .badge {
                    border: 2px solid #333;
                    padding: 20px;
                    border-radius: 8px;
                    max-width: 400px;
                    margin: 0 auto;
                }
                .title {
                    font-size: 18px;
                    font-weight: bold;
                    margin-bottom: 15px;
                    color: #2c3e50;
                }
                .qr-section {
                    margin: 20px 0;
                }
                .qr-section img {
                    width: 200px;
                    height: 200px;
                    border: 1px solid #ddd;
                    padding: 5px;
                }
                .child-info {
                    text-align: left;
                    margin-top: 15px;
                    font-size: 12px;
                }
                .child-info p {
                    margin: 5px 0;
                    line-height: 1.5;
                }
                .label {
                    font-weight: bold;
                    color: #2c3e50;
                }
                .message {
                    margin-top: 20px;
                    font-size: 11px;
                    color: #555;
                    font-style: italic;
                    line-height: 1.6;
                }
            </style>
        </head>
        <body>
            <div class="badge">
                <div class="title">GAFETE DE IDENTIFICACIÓN</div>

                {$this->buildQrSectionHtml($qrUrl)}

                <div class="child-info">
                    <p><span class="label">Nombre:</span> {$child->full_name}</p>
                    <p><span class="label">Código:</span> {$child->code}</p>
                    <p><span class="label">Iglesia:</span> {$churchName}</p>
                    <p><span class="label">Comunidad:</span> {$communityName}</p>
                </div>

                <div class="message">
                    Este es tu gafete de identificación para tus asistencias a misas.
                    Por favor guárdalo y preséntalo cuando sea necesario o se te indique.
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Construye la sección del QR en HTML, omitiendo si no está disponible
     */
    private function buildQrSectionHtml(string $qrUrl): string
    {
        if (!$qrUrl) {
            return '<div class="qr-section"><p style="color: #999; font-size: 11px;">QR no disponible</p></div>';
        }

        return <<<HTML
        <div class="qr-section">
            <img src="{$qrUrl}" alt="QR Code">
        </div>
        HTML;
    }

    /**
     * Genera contenido PDF desde HTML de forma simple
     */
    private function generatePdfContent(string $html): string
    {
        // Intentar usar wkhtmltopdf si existe
        if ($this->hasWkhtmltopdf()) {
            return $this->generatePdfWithWkhtmltopdf($html);
        }

        // Fallback: usar dompdf (ya instalado en el proyecto)
        return $this->generatePdfWithDompdf($html);
    }

    /**
     * Genera PDF usando dompdf directamente (sin facade)
     */
    private function generatePdfWithDompdf(string $html): string
    {
        try {
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A5', 'portrait');
            $dompdf->render();

            return $dompdf->output();
        } catch (Exception $e) {
            Log::warning('Error generando PDF con dompdf', ['error' => $e->getMessage()]);

            // Fallback: generar PDF mínimo como último recurso
            return $this->generateMinimalPdf($html);
        }
    }

    /**
     * Verifica si wkhtmltopdf está disponible
     */
    private function hasWkhtmltopdf(): bool
    {
        try {
            $output = [];
            $returnCode = 0;

            // Windows: usar where, Linux/Mac: usar which
            $command = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
                ? 'where wkhtmltopdf'
                : 'which wkhtmltopdf';

            @exec($command, $output, $returnCode);

            return $returnCode === 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Genera PDF usando wkhtmltopdf
     */
    private function generatePdfWithWkhtmltopdf(string $html): string
    {
        try {
            $tempHtmlFile = tempnam(sys_get_temp_dir(), 'badge_') . '.html';
            $tempPdfFile = tempnam(sys_get_temp_dir(), 'badge_') . '.pdf';

            file_put_contents($tempHtmlFile, $html);

            $command = "wkhtmltopdf \"{$tempHtmlFile}\" \"{$tempPdfFile}\" 2>&1";
            exec($command);

            if (file_exists($tempPdfFile) && filesize($tempPdfFile) > 0) {
                $content = file_get_contents($tempPdfFile);
                @unlink($tempPdfFile);
                @unlink($tempHtmlFile);

                return $content;
            }
        } catch (Exception $e) {
            Log::warning('Error generando PDF con wkhtmltopdf', ['error' => $e->getMessage()]);
        }

        return $this->generateMinimalPdf($html);
    }

    /**
     * Genera un PDF mínimo válido sin dependencias
     */
    private function generateMinimalPdf(string $html): string
    {
        // PDF mínimo válido (simplificado)
        $pdf = "%PDF-1.0\n";
        $pdf .= "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n";
        $pdf .= "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n";
        $pdf .= "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n";
        $pdf .= "xref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000058 00000 n\n0000000115 00000 n\n";
        $pdf .= "trailer<</Size 4/Root 1 0 R>>\nstartxref\n190\n%%EOF\n";

        return $pdf;
    }

    /**
     * Envía el QR por WhatsApp como imagen con mensaje de bienvenida
     */
    private function sendViaWhatsapp(Child $child, string $phoneNumber, string $qrImagePath): void
    {
        try {
            $child->loadMissing(['church:id,name', 'community:id,name']);
            $churchName = $child->church?->name ?? '';
            $communityName = $child->community?->name ?? '';

            $caption = "¡Bienvenido(a) {$child->full_name}! 🎉\n\n"
                . "Este es tu código QR de identificación para la catequesis.\n"
                . "📖 Código: {$child->code}\n"
                . ($churchName ? "⛪ Iglesia: {$churchName}\n" : '')
                . ($communityName ? "🏘️ Comunidad: {$communityName}\n" : '')
                . "\nGuárdalo y preséntalo cuando sea necesario o se te indique.";

            $result = $this->whatsappService->uploadAndSendImage(
                toPhone: $phoneNumber,
                storagePath: $qrImagePath,
                caption: $caption
            );

            Log::info('Gafete QR enviado por WhatsApp', [
                'child_id' => $child->id,
                'child_code' => $child->code,
                'media_id' => $result['media_id'] ?? null,
            ]);

            // Limpiar imagen temporal
            if (Storage::exists($qrImagePath)) {
                Storage::delete($qrImagePath);
            }
        } catch (Throwable $e) {
            Log::error('Error enviando WhatsApp', [
                'child_id' => $child->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Obtiene y normaliza el teléfono del niño
     */
    private function getNormalizedPhone(Child $child): ?string
    {
        $phone = $child->phone;
        $lada = $child->phone_lada;

        if (! $phone || ! $lada) {
            return null;
        }

        return "{$lada}{$phone}";
    }

    /**
     * Extrae el código de país del teléfono del niño
     */
    private function extractCountryCode(Child $child): string
    {
        return $child->phone_lada ?? '+52';
    }

    /**
     * Verifica si WhatsApp está configurado
     */
    private function isConfigured(): bool
    {
        return (bool) config('meta.whatsapp.token') && (bool) config('meta.whatsapp.phone_number_id');
    }

    /**
     * Enmasca el teléfono para logs
     */
    private function maskPhone(string $phone): string
    {
        return substr($phone, 0, 3) . '***' . substr($phone, -4);
    }
}
