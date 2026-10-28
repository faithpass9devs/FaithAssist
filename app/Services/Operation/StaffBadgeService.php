<?php

namespace App\Services\Operation;

use App\Models\Operation\Staff;
use App\Services\Settings\SettingResolver;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

class StaffBadgeService
{
    public function __construct(private readonly SettingResolver $settings) {}

    public function generateStaffBadgePdf(Staff $staff): string
    {
        $staff->loadMissing(['staffType:id,name', 'church:id,name,deanery_id', 'church.deanery:id,diocese_id', 'community:id,name']);

        $churchId = $staff->church?->id;
        $dioceseId = $staff->church?->deanery?->diocese_id;

        $logo = $this->resolveFile('staff_badge.logo_image', $churchId, $dioceseId)
            ?? $this->resolveDefaultLogo();
        $background = $this->resolveFile('staff_badge.background_1', $churchId, $dioceseId)
            ?? $this->resolveDefaultBackground();

        $title = $this->resolveText('staff_badge.title_text', 'Gafete de personal', $churchId, $dioceseId);
        $accentColor = $this->resolveColor('staff_badge.accent_color', '#2563eb', $churchId, $dioceseId);
        $bodyTextColor = $this->resolveColor('staff_badge.body_text_color', '#111827', $churchId, $dioceseId);
        $qrBorderColor = $this->resolveColor('staff_badge.qr_border_color', '#d1d5db', $churchId, $dioceseId);
        $chipBgColor = $this->resolveColor('staff_badge.chip_bg_color', '#111827', $churchId, $dioceseId);
        $chipTextColor = $this->resolveColor('staff_badge.chip_text_color', '#ffffff', $churchId, $dioceseId);

        $qrDataUri = $this->generateQrDataUri($staff->code);

        $html = view('pdf.operation.staff-badge', [
            'staffName' => trim(collect([$staff->name, $staff->paterno, $staff->materno])->filter()->implode(' ')),
            'staffCode' => $staff->code,
            'staffTypeName' => $staff->staffType?->name ?? 'Sin tipo',
            'churchName' => $staff->church?->name ?? 'No especificada',
            'communityName' => $staff->community?->name ?? 'No especificada',
            'phone' => $this->formatPhone($staff->phone_lada, $staff->phone),
            'birthdate' => $staff->birthdate?->format('Y-m-d') ?? '',
            'qrImageUrl' => $qrDataUri,
            'badgeLogoPath' => $logo,
            'backgroundPath' => $background,
            'badgeTitleText' => $title,
            'accentColor' => $accentColor,
            'bodyTextColor' => $bodyTextColor,
            'qrBorderColor' => $qrBorderColor,
            'chipBgColor' => $chipBgColor,
            'chipTextColor' => $chipTextColor,
        ])->render();

        return $this->generatePdf($html);
    }

    private function resolveText(string $key, string $fallback, ?int $churchId, ?int $dioceseId): string
    {
        $value = $this->settings->resolve($key, $churchId, $dioceseId);

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    private function resolveColor(string $key, string $fallback, ?int $churchId, ?int $dioceseId): string
    {
        $value = $this->settings->resolve($key, $churchId, $dioceseId);

        return (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', trim($value))) ? trim($value) : $fallback;
    }

    private function resolveFile(string $key, ?int $churchId, ?int $dioceseId): ?string
    {
        $storedPath = $this->settings->resolve($key, $churchId, $dioceseId);

        if (! is_string($storedPath) || trim($storedPath) === '') {
            return null;
        }

        $absolutePath = Storage::disk('local')->path($storedPath);
        $realPath = realpath($absolutePath);

        if (! $realPath || ! file_exists($realPath)) {
            return null;
        }

        return 'file://'.str_replace('\\', '/', $realPath);
    }

    private function resolveDefaultLogo(): ?string
    {
        foreach ([
            public_path('images/virgen.png'),
            public_path('images/virgen.jpg'),
            public_path('images/virgen.jpeg'),
        ] as $logoPath) {
            if (file_exists($logoPath)) {
                return 'file://'.str_replace('\\', '/', $logoPath);
            }
        }

        return null;
    }

    private function resolveDefaultBackground(): ?string
    {
        foreach ([
            public_path('images/background_1.png'),
            public_path('images/background_1.jpg'),
            public_path('images/background_1.jpeg'),
        ] as $backgroundPath) {
            if (file_exists($backgroundPath)) {
                return 'file://'.str_replace('\\', '/', $backgroundPath);
            }
        }

        return null;
    }

    private function generateQrDataUri(string $code): ?string
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: $code,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 320,
            margin: 16,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        $png = $result->getString();

        if (! is_string($png) || $png === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function generatePdf(string $html): string
    {
        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function formatPhone(?string $lada, ?string $phone): string
    {
        $lada = preg_replace('/\D/', '', (string) $lada) ?: '';
        $phone = preg_replace('/\D/', '', (string) $phone) ?: '';

        if ($phone === '') {
            return '';
        }

        return $lada !== '' ? '+'.$lada.' '.$phone : $phone;
    }
}
