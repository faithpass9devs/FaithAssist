<?php

namespace App\Services\Catechism;

use App\Models\Catechism\Child;
use App\Services\Settings\SettingResolver;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ChildQrWhatsappService
{
    public function __construct(private readonly SettingResolver $settings) {}

    /**
     * Genera el PDF del gafete del niño para abrirse en navegador.
     */
    public function generateChildBadgePdf(Child $child, ?string $qrImageDataUrl = null): string
    {
        $child->loadMissing([
            'church.municipality.state',
            'community.municipality.state',
            'activeLevelAssignments.level',
        ]);

        $qrSvg = $this->generateQrSvgMarkup($child->code);
        $qrMatrixHtml = $this->generateQrMatrixHtml($child->code);
        $qrStoragePath = $this->generateQrImage($child->code);

        try {
            $qrImageUrl = $this->buildPdfLocalFileUrl($qrStoragePath)
                ?? $this->generateQrDataUri($child->code)
                ?? $this->sanitizePdfQrImage($qrImageDataUrl);

            $viewData = $this->buildBadgeViewData(
                $child,
                $qrImageUrl,
                $qrSvg,
                $qrMatrixHtml,
                $this->resolveBadgeSettings($child)
            );
            $html = view('pdf.catechism.child-badge', $viewData)->render();

            return $this->generatePdfContent($html);
        } finally {
            if ($qrStoragePath && Storage::exists($qrStoragePath)) {
                Storage::delete($qrStoragePath);
            }
        }
    }

    private function buildPdfLocalFileUrl(?string $storagePath): ?string
    {
        if (! $storagePath) {
            return null;
        }

        $absolutePath = Storage::path($storagePath);
        $realPath = realpath($absolutePath);

        if (! $realPath || ! file_exists($realPath)) {
            return null;
        }

        return 'file://'.str_replace('\\', '/', $realPath);
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
            $pdfPath = "exports/gafetes/{$filename}";

            // Usar exec con wkhtmltopdf si está disponible, sino usar HTML simple
            $pdfContent = $this->generatePdfContent($html);

            // Validar que el PDF tiene contenido
            if (! $pdfContent || strlen($pdfContent) < 100) {
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
     * Construye el HTML del PDF con QR y datos del niño
     */
    private function buildPdfHtml(Child $child, string $qrImagePath): string
    {
        // Usar ruta absoluta con file:// para que funcione en cualquier contexto
        $qrAbsolutePath = Storage::path($qrImagePath);
        $qrUrl = file_exists($qrAbsolutePath) ? 'file://'.realpath($qrAbsolutePath) : '';

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
     * Prepara la información que necesita la vista del gafete.
     */
    private function buildBadgeViewData(
        Child $child,
        ?string $qrImageUrl = null,
        ?string $qrSvg = null,
        ?string $qrMatrixHtml = null,
        array $badgeSettings = []
    ): array {
        $church = $child->church;
        $community = $child->community;
        $municipality = $church?->municipality ?? $community?->municipality;
        $state = $municipality?->state;
        $levels = $child->activeLevelAssignments
            ->map(fn ($assignment) => $assignment->level?->name)
            ->filter()
            ->values();

        return array_merge([
            'childName' => $this->resolveFullName($child),
            'childCode' => $child->code,
            'badgeLogoPath' => $badgeSettings['badgeLogoPath'] ?? $this->resolveBadgeLogoPath(),
            'pageOneBackgroundPath' => $badgeSettings['pageOneBackgroundPath'] ?? $this->resolveBackgroundTemplatePath('background_1'),
            'pageTwoBackgroundPath' => $badgeSettings['pageTwoBackgroundPath'] ?? $this->resolveBackgroundTemplatePath('background_2'),
            'churchName' => $church?->name ?? 'No especificada',
            'municipalityName' => $municipality?->name ?? 'No especificado',
            'stateName' => $state?->short_name ?? $state?->name ?? '',
            'communityName' => $community?->name ?? 'No especificada',
            'levelName' => $levels->isNotEmpty() ? $levels->implode(', ') : 'Sin nivel',
            'qrImageUrl' => $qrImageUrl,
            'qrSvg' => $qrSvg,
            'qrMatrixHtml' => $qrMatrixHtml,
            'attendanceMonths' => [
                'Septiembre',
                'Octubre',
                'Noviembre',
                'Diciembre',
                'Enero',
                'Febrero',
                'Marzo',
                'Abril',
                'Mayo',
                'Junio',
                'Julio',
                'Agosto',
            ],
            'weekHeaders' => ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4', 'Semana 5'],
        ], Arr::except($badgeSettings, [
            'badgeLogoPath',
            'pageOneBackgroundPath',
            'pageTwoBackgroundPath',
        ]));
    }

    /**
     * Resuelve los ajustes del gafete aplicables a la parroquia del niño.
     *
     * @return array<string, mixed>
     */
    private function resolveBadgeSettings(Child $child): array
    {
        $churchId = $child->church?->id;
        $dioceseId = $child->church?->diocese_id;

        return [
            'badgeLogoPath' => $this->resolveBadgeFileSetting('badge.logo_image', $child),
            'pageOneBackgroundPath' => $this->resolveBadgeFileSetting('badge.background_1', $child),
            'pageTwoBackgroundPath' => $this->resolveBadgeFileSetting('badge.background_2', $child),
            'badgeTitleText' => $this->resolveBadgeTextSetting('badge.title_text', 'Gafete de catequesis', $churchId, $dioceseId),
            'footerText' => $this->resolveBadgeTextSetting(
                'badge.footer_text',
                'Para registrar su asistencia dominical, será necesario presentar el código QR asignado al momento de su llegada. Sin este código, no podrá ser validada su participación.',
                $churchId,
                $dioceseId
            ),
            'accentColor' => $this->resolveBadgeColorSetting('badge.accent_color', '#d4af37', $churchId, $dioceseId),
            'bodyTextColor' => $this->resolveBadgeColorSetting('badge.body_text_color', '#111827', $churchId, $dioceseId),
            'qrBorderColor' => $this->resolveBadgeColorSetting('badge.qr_border_color', '#d1d5db', $churchId, $dioceseId),
            'chipBgColor' => $this->resolveBadgeColorSetting('badge.chip_bg_color', '#111827', $churchId, $dioceseId),
            'chipTextColor' => $this->resolveBadgeColorSetting('badge.chip_text_color', '#ffffff', $churchId, $dioceseId),
            'tableHeaderColor' => $this->resolveBadgeColorSetting('badge.table_header_color', '#d892ad', $churchId, $dioceseId),
            'tableBorderColor' => $this->resolveBadgeColorSetting('badge.table_border_color', '#e5b7c6', $churchId, $dioceseId),
        ];
    }

    private function resolveBadgeFileSetting(string $key, Child $child): ?string
    {
        $storedPath = $this->settings->resolve($key, $child->church?->id, $child->church?->diocese_id);

        if (! is_string($storedPath) || trim($storedPath) === '') {
            return null;
        }

        return $this->prepareStoredPdfImage($storedPath);
    }

    private function resolveBadgeTextSetting(string $key, string $fallback, ?int $churchId, ?int $dioceseId): string
    {
        $value = $this->settings->resolve($key, $churchId, $dioceseId);

        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        return trim($value);
    }

    private function resolveBadgeColorSetting(string $key, string $fallback, ?int $churchId, ?int $dioceseId): string
    {
        $value = $this->settings->resolve($key, $churchId, $dioceseId);

        return (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', trim($value))) ? trim($value) : $fallback;
    }

    /**
     * Convierte un archivo almacenado en disco a una ruta file:// normalizada para dompdf.
     */
    private function prepareStoredPdfImage(string $storedPath): ?string
    {
        $absolutePath = Storage::disk('local')->path($storedPath);
        $realPath = realpath($absolutePath);

        if (! $realPath || ! file_exists($realPath)) {
            return null;
        }

        return $this->preparePdfTemplateImagePath($realPath);
    }

    private function resolveBadgeLogoPath(): ?string
    {
        $candidates = [
            public_path('images/virgen.png'),
            public_path('images/virgen.jpg'),
            public_path('images/virgen.jpeg'),
        ];

        foreach ($candidates as $logoPath) {
            if (file_exists($logoPath)) {
                return str_replace('\\', '/', $logoPath);
            }
        }

        return null;
    }

    private function resolveBackgroundTemplatePath(string $baseName): ?string
    {
        $candidates = [
            public_path('images/'.$baseName.'.png'),
            public_path('images/'.$baseName.'.jpg'),
            public_path('images/'.$baseName.'.jpeg'),
        ];

        foreach ($candidates as $backgroundPath) {
            if (file_exists($backgroundPath)) {
                return $this->preparePdfTemplateImagePath($backgroundPath);
            }
        }

        return null;
    }

    private function preparePdfTemplateImagePath(string $sourcePath): ?string
    {
        $normalizedSource = str_replace('\\', '/', $sourcePath);
        $extension = strtolower(pathinfo($normalizedSource, PATHINFO_EXTENSION));

        if ($extension !== 'png') {
            return 'file://'.$normalizedSource;
        }

        try {
            $image = @imagecreatefrompng($sourcePath);

            if (! $image) {
                return 'file://'.$normalizedSource;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $canvas = imagecreatetruecolor($width, $height);

            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
            imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

            $targetDir = storage_path('app/private/pdf_templates');

            if (! is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }

            $targetPath = $targetDir.'/'.pathinfo($sourcePath, PATHINFO_FILENAME).'.jpg';
            imagejpeg($canvas, $targetPath, 92);

            imagedestroy($canvas);
            imagedestroy($image);

            if (file_exists($targetPath)) {
                return 'file://'.str_replace('\\', '/', $targetPath);
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo normalizar plantilla PNG para PDF', [
                'path' => $sourcePath,
                'error' => $e->getMessage(),
            ]);
        }

        return 'file://'.$normalizedSource;
    }

    private function generateQrSvgMarkup(string $childCode): ?string
    {
        try {
            $result = (new Builder(
                writer: new SvgWriter,
                data: $childCode,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 320,
                margin: 0,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
            ))->build();

            $svgRaw = trim($result->getString());

            if ($svgRaw === '') {
                return null;
            }

            $svgStart = stripos($svgRaw, '<svg');

            if ($svgStart === false) {
                return null;
            }

            $svg = substr($svgRaw, $svgStart);

            return $svg;
        } catch (Throwable $e) {
            Log::error('Error generando SVG QR para PDF', [
                'child_code' => $childCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Genera un PNG local del QR usando Endroid para que Dompdf lo renderice sin depender de red.
     */
    private function generateQrImage(string $childCode): ?string
    {
        try {
            $result = $this->buildQrResult($childCode);

            if (! $result) {
                return null;
            }

            $filename = 'qr_'.$childCode.'_'.time().'.png';
            $path = 'pdf/temp_qr/'.$filename;
            $pngBinary = $this->normalizePngBinary($result->getString());

            if ($pngBinary === null) {
                return null;
            }

            Storage::makeDirectory('pdf/temp_qr');
            Storage::put($path, $pngBinary);

            return $path;
        } catch (Throwable $e) {
            Log::error('Error generando QR PNG local', [
                'child_code' => $childCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function normalizePngBinary(string $pngBinary): ?string
    {
        try {
            $image = @imagecreatefromstring($pngBinary);

            if (! $image) {
                Log::warning('No se pudo abrir binario PNG del QR para normalizacion');

                return null;
            }

            imagepalettetotruecolor($image);
            imagesavealpha($image, true);

            ob_start();
            imagepng($image, null, 9);
            $normalized = ob_get_clean();
            imagedestroy($image);

            if (! is_string($normalized) || $normalized === '') {
                return null;
            }

            return $normalized;
        } catch (Throwable $e) {
            Log::warning('Error normalizando PNG del QR', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function generateQrDataUri(string $childCode): ?string
    {
        $result = $this->buildQrResult($childCode);

        if (! $result) {
            return null;
        }

        $pngBinary = $result->getString();

        if (! is_string($pngBinary) || $pngBinary === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($pngBinary);
    }

    private function generateQrMatrixHtml(string $childCode): ?string
    {
        $result = $this->buildQrResult($childCode, 0);

        if (! $result) {
            return null;
        }

        $matrix = $result->getMatrix();
        $blocks = $matrix->getBlockCount();

        if ($blocks <= 0) {
            return null;
        }

        // Ajusta el QR para que ocupe el recuadro casi completo sin desbordar.
        $targetPixels = 204;
        $cellSize = max(4, min(8, (int) round($targetPixels / $blocks)));
        $html = '<table cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 auto;background:#fff;line-height:0;font-size:0;">';

        for ($row = 0; $row < $blocks; $row++) {
            $html .= '<tr>';

            for ($col = 0; $col < $blocks; $col++) {
                $value = $matrix->getBlockValue($row, $col);
                $color = $value === 1 ? '#000000' : '#ffffff';
                $html .= '<td style="width:'.$cellSize.'px;height:'.$cellSize.'px;background:'.$color.';padding:0;margin:0;"></td>';
            }

            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    private function sanitizePdfQrImage(?string $qrImageDataUrl): ?string
    {
        if (! is_string($qrImageDataUrl)) {
            return null;
        }

        $qrImageDataUrl = trim($qrImageDataUrl);

        if ($qrImageDataUrl === '' || ! str_starts_with($qrImageDataUrl, 'data:image/png;base64,')) {
            return null;
        }

        // Protege de URLs excesivamente grandes o payloads no válidos.
        if (strlen($qrImageDataUrl) > 200000) {
            return null;
        }

        $payload = substr($qrImageDataUrl, strlen('data:image/png;base64,'));
        $decoded = base64_decode($payload, true);

        if ($decoded === false || $decoded === '') {
            return null;
        }

        $image = @imagecreatefromstring($decoded);

        if ($image === false) {
            return null;
        }

        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($decoded);
    }

    private function buildQrResult(string $childCode, int $margin = 16)
    {
        try {
            return (new Builder(
                writer: new PngWriter,
                data: $childCode,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 320,
                margin: max(0, $margin),
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
            ))->build();
        } catch (Throwable $e) {
            Log::error('Error construyendo QR', [
                'child_code' => $childCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resuelve el nombre completo del niño sin depender de un accessor serializado.
     */
    private function resolveFullName(Child $child): string
    {
        return trim(collect([$child->name, $child->paterno, $child->materno])->filter()->implode(' '));
    }

    /**
     * Construye la sección del QR en HTML, omitiendo si no está disponible
     */
    private function buildQrSectionHtml(string $qrUrl): string
    {
        if (! $qrUrl) {
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
        // Usar dompdf para el gafete; wkhtmltopdf en Windows ha mostrado
        // placeholder de imagen rota con el QR incrustado.
        return $this->generatePdfWithDompdf($html);
    }

    /**
     * Genera PDF usando dompdf directamente (sin facade)
     */
    private function generatePdfWithDompdf(string $html): string
    {
        try {
            $options = new Options;
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('chroot', base_path());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
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
            $tempHtmlFile = tempnam(sys_get_temp_dir(), 'badge_').'.html';
            $tempPdfFile = tempnam(sys_get_temp_dir(), 'badge_').'.pdf';

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

}
