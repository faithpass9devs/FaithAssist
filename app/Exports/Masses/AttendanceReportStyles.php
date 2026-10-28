<?php

namespace App\Exports\Masses;

use App\Services\Masses\AttendanceReportService;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\BorderStyle;
use OpenSpout\Common\Entity\Style\BorderWidth;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;

/**
 * Paleta y estilos reutilizables del reporte de asistencias.
 *
 * Los estilos de OpenSpout son inmutables; aquí se instancian una sola vez para que
 * el registro de estilos del libro se mantenga pequeño aunque haya miles de filas.
 */
final class AttendanceReportStyles
{
    public const NAVY = '0F172A';

    public const SKY = '0369A1';

    public const SLATE_LIGHT = 'F1F5F9';

    public const SLATE_BORDER = 'CBD5E1';

    public const MUTED = '64748B';

    public const WHITE = 'FFFFFF';

    public const PRESENT_BG = 'DCFCE7';

    public const PRESENT_FG = '166534';

    public const ABSENT_BG = 'FEE2E2';

    public const ABSENT_FG = '991B1B';

    public const JUSTIFIED_BG = 'FEF3C7';

    public const JUSTIFIED_FG = '92400E';

    public const NA_BG = 'F8FAFC';

    public const NA_FG = '94A3B8';

    /** @var array<string, array{bg: string, fg: string}> */
    public const CELL_COLORS = [
        AttendanceReportService::CELL_PRESENT => ['bg' => self::PRESENT_BG, 'fg' => self::PRESENT_FG],
        AttendanceReportService::CELL_ABSENT => ['bg' => self::ABSENT_BG, 'fg' => self::ABSENT_FG],
        AttendanceReportService::CELL_JUSTIFIED => ['bg' => self::JUSTIFIED_BG, 'fg' => self::JUSTIFIED_FG],
        AttendanceReportService::CELL_NOT_APPLICABLE => ['bg' => self::NA_BG, 'fg' => self::NA_FG],
    ];

    /** @var array<string, array{bg: string, fg: string}> */
    public const STATUS_COLORS = [
        AttendanceReportService::STATUS_REGULAR => ['bg' => self::PRESENT_BG, 'fg' => self::PRESENT_FG],
        AttendanceReportService::STATUS_AT_RISK => ['bg' => self::JUSTIFIED_BG, 'fg' => self::JUSTIFIED_FG],
        AttendanceReportService::STATUS_BLOCKED => ['bg' => self::ABSENT_BG, 'fg' => self::ABSENT_FG],
    ];

    /** @var array<string, string> */
    public const CELL_LEGEND = [
        AttendanceReportService::CELL_PRESENT => 'Presente (registró entrada en al menos una misa, cualquiera de los dos días)',
        AttendanceReportService::CELL_ABSENT => 'Falta (no asistió a ninguna misa en ningún día del fin de semana)',
        AttendanceReportService::CELL_JUSTIFIED => 'Justificada (incidencia activa; no cuenta como falta)',
        AttendanceReportService::CELL_NOT_APPLICABLE => 'No aplica (sin misa para su comunidad o aún no inscrito)',
    ];

    /** @var array<string, string> */
    public const STATUS_LEGEND = [
        AttendanceReportService::STATUS_REGULAR => 'Faltas por debajo del límite menos uno.',
        AttendanceReportService::STATUS_AT_RISK => 'Le queda exactamente una falta antes de alcanzar el límite.',
        AttendanceReportService::STATUS_BLOCKED => 'Alcanzó o superó el límite de faltas: ya no tiene derecho a registrar asistencia.',
    ];

    public readonly Style $title;

    public readonly Style $muted;

    public readonly Style $sectionHeader;

    public readonly Style $tableHeader;

    public readonly Style $tableHeaderSky;

    public readonly Style $label;

    public readonly Style $body;

    public readonly Style $bodyLeft;

    public readonly Style $bodyCenter;

    public readonly Style $bodyPercent;

    /** @var array<string, Style> */
    public readonly array $cells;

    /** @var array<string, Style> */
    public readonly array $statuses;

    /** @var array<string, Style> */
    public readonly array $statusesBold;

    /** @var array<string, Style> */
    public readonly array $legendKeys;

    public function __construct()
    {
        $border = self::border();

        $this->title = (new Style)->withFontBold(true)->withFontSize(16)->withFontColor(self::NAVY)
            ->withCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $this->muted = (new Style)->withFontColor(self::MUTED);

        $header = (new Style)->withFontBold(true)->withFontColor(self::WHITE)
            ->withCellAlignment(CellAlignment::CENTER)
            ->withCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->withShouldWrapText(true);
        $this->sectionHeader = $header->withBackgroundColor(self::NAVY)->withCellAlignment(CellAlignment::LEFT);
        $this->tableHeader = $header->withBackgroundColor(self::NAVY)->withBorder($border);
        $this->tableHeaderSky = $header->withBackgroundColor(self::SKY)->withBorder($border);

        $this->body = (new Style)->withBorder($border)->withCellVerticalAlignment(CellVerticalAlignment::CENTER);
        // Alineación explícita a la izquierda para que los números no se vayan a la derecha.
        $this->bodyLeft = $this->body->withCellAlignment(CellAlignment::LEFT);
        $this->bodyCenter = $this->body->withCellAlignment(CellAlignment::CENTER);
        $this->bodyPercent = $this->bodyCenter->withFormat('0.0%');
        $this->label = $this->body->withBackgroundColor(self::SLATE_LIGHT);

        $cells = [];
        foreach (self::CELL_COLORS as $code => $colors) {
            $cells[$code] = $this->bodyCenter->withBackgroundColor($colors['bg'])->withFontColor($colors['fg']);
        }
        $this->cells = $cells;

        $statuses = [];
        $statusesBold = [];
        $legendKeys = [];
        foreach (self::STATUS_COLORS as $status => $colors) {
            $statuses[$status] = $this->bodyCenter->withBackgroundColor($colors['bg'])->withFontColor($colors['fg']);
            $statusesBold[$status] = $statuses[$status]->withFontBold(true);
            $legendKeys[$status] = $statusesBold[$status];
        }
        foreach (self::CELL_COLORS as $code => $colors) {
            $legendKeys[$code] = $this->cells[$code]->withFontBold(true);
        }
        $this->statuses = $statuses;
        $this->statusesBold = $statusesBold;
        $this->legendKeys = $legendKeys;
    }

    private static function border(): Border
    {
        $part = fn (BorderName $name): BorderPart => new BorderPart($name, self::SLATE_BORDER, BorderWidth::THIN, BorderStyle::SOLID);

        return new Border($part(BorderName::TOP), $part(BorderName::RIGHT), $part(BorderName::BOTTOM), $part(BorderName::LEFT));
    }
}
