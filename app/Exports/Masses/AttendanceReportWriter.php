<?php

namespace App\Exports\Masses;

use App\Services\Masses\AttendanceReportService;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Comment\Comment;
use OpenSpout\Common\Entity\Comment\TextRun;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use ZipArchive;

/**
 * Escribe el reporte de asistencias en XLSX por flujo (OpenSpout): cada fila se vuelca
 * al disco en cuanto se construye, por lo que la memoria no crece con el número de
 * niños ni de fines de semana. Hoja 1 «Asistencias» (lista de niños), hoja 2 «Indicadores».
 */
class AttendanceReportWriter
{
    private const SUMMARY_COLUMNS = 8;

    private const DETAIL_FIXED_HEADERS = ['#', 'Código', 'Niño', 'Nivel', 'Comunidad'];

    private const DETAIL_TOTAL_HEADERS = ['Asistencias', 'Faltas', 'Justificadas', '% Asistencia', 'Faltas restantes', 'Estatus'];

    private const DETAIL_HEADER_ROW = 3;

    private AttendanceReportStyles $styles;

    private Options $options;

    private Writer $writer;

    private int $sheetIndex = 0;

    private int $rowNumber = 0;

    /**
     * Carpeta de trabajo propia de la app para el libro y los temporales de OpenSpout.
     * Se evita el temp del sistema porque en algunos servidores no es accesible para PHP-FPM.
     */
    public function workingDirectory(): string
    {
        $directory = storage_path('app'.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'attendance-reports');

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear la carpeta de trabajo del reporte: {$directory}");
        }

        if (! is_writable($directory)) {
            throw new RuntimeException("La carpeta de trabajo del reporte no tiene permisos de escritura: {$directory}");
        }

        $this->purgeStaleFiles($directory);

        return $directory;
    }

    public function createWorkingFile(): string
    {
        $path = $this->workingDirectory().DIRECTORY_SEPARATOR.'r-asistencias-'.bin2hex(random_bytes(8)).'.xlsx';

        if (@file_put_contents($path, '') === false) {
            throw new RuntimeException("No se pudo crear el archivo temporal del reporte: {$path}");
        }

        return $path;
    }

    /** Anchos de la hoja Indicadores (columna 1-based => ancho). */
    private const SUMMARY_WIDTHS = [1 => 38, 2 => 22, 3 => 17, 4 => 17, 5 => 17, 6 => 17, 7 => 17, 8 => 17];

    /**
     * @param  array<string, mixed>  $report  Resultado de AttendanceReportService::build().
     */
    public function write(array $report, string $path): void
    {
        $this->styles = new AttendanceReportStyles;
        $this->options = new Options(
            tempFolder: $this->workingDirectory(),
            pageSetup: new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, fitToHeight: 0, fitToWidth: 1),
        );
        $this->writer = new Writer($this->options);
        $this->writer->openToFile($path);

        try {
            $this->writeDetail($report);
            $this->writer->addNewSheetAndMakeItCurrent();
            $this->sheetIndex = 1;
            $this->rowNumber = 0;
            $this->writeSummary($report);
        } finally {
            $this->writer->close();
        }

        // OpenSpout aplica los mismos anchos de columna a todas las hojas; la hoja
        // Indicadores (pequeña) se ajusta reescribiendo su bloque <cols> en el XML.
        $this->overrideSheetColumnWidths($path, 2, self::SUMMARY_WIDTHS);
    }

    /**
     * @param  array<int, float|int>  $widths  columna 1-based => ancho
     */
    private function overrideSheetColumnWidths(string $path, int $sheetNumber, array $widths): void
    {
        $entry = "xl/worksheets/sheet{$sheetNumber}.xml";
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("No se pudo abrir el libro generado para ajustar las columnas: {$path}");
        }

        try {
            $xml = $zip->getFromName($entry);

            if ($xml === false) {
                return;
            }

            $cols = '<cols>';
            foreach ($widths as $column => $width) {
                $cols .= sprintf('<col min="%d" max="%d" width="%s" customWidth="true"/>', $column, $column, $width);
            }
            $cols .= '</cols>';

            $patched = preg_replace('/<cols>.*?<\/cols>/s', $cols, $xml, 1, $replaced);

            if ($replaced === 0) {
                $patched = str_replace('<sheetData', $cols.'<sheetData', $xml);
            }

            $zip->addFromString($entry, $patched);
        } finally {
            $zip->close();
        }
    }

    /** Limpia restos de descargas interrumpidas con más de una hora de antigüedad. */
    private function purgeStaleFiles(string $directory): void
    {
        $threshold = time() - 3600;

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $entry) {
            if (@filemtime($entry) >= $threshold) {
                continue;
            }

            if (is_dir($entry)) {
                $this->deleteDirectory($entry);
            } else {
                @unlink($entry);
            }
        }
    }

    private function deleteDirectory(string $directory): void
    {
        foreach (glob($directory.DIRECTORY_SEPARATOR.'{,.}[!.,!..]*', GLOB_BRACE) ?: [] as $entry) {
            is_dir($entry) ? $this->deleteDirectory($entry) : @unlink($entry);
        }

        @rmdir($directory);
    }

    public function fileName(array $report): string
    {
        $church = preg_replace('/[^A-Za-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $report['church']['name']) ?: 'parroquia');
        $period = preg_replace('/[^A-Za-z0-9_-]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $report['period_slug']) ?: 'periodo');

        return 'R-Asistencias_'.trim($period, '_').'_'.trim(mb_strtolower($church), '_').'.xlsx';
    }

    // ───────────────────────────── Hoja Indicadores ─────────────────────────────

    private function writeSummary(array $report): void
    {
        $summary = $report['summary'];
        $filters = $report['filters'];
        $sheet = $this->writer->getCurrentSheet();
        $sheet->setName('Indicadores');

        $this->mergedRow('Reporte de asistencias — '.$report['church']['name'], $this->styles->title, height: 28);
        $this->blank();

        $this->keyValueBlock('Parámetros del reporte', [
            ['Periodo', $report['period_label']],
            ['Comunidad', $filters['community'] ?? 'Todas'],
            ['Nivel', $filters['level'] ?? 'Todos'],
            ['Niños incluidos', $filters['child_status']],
            ['Límite de faltas', $report['absence_limit'].' falta(s) — a partir de este número el niño queda Sin derecho'],
            ['Fines de semana evaluados', $summary['weekends']],
            ['Generado el', $report['generated_at']->format('d/m/Y')],
            ['Generado por', $report['generated_by']],
        ]);
        $this->blank();

        $this->kpiBlock($summary);
        $this->blank();

        $this->table(
            'Desglose por nivel',
            ['Nivel', 'Niños', 'Regulares', 'En riesgo', 'Sin derecho', '% asistencia'],
            array_map(fn (array $level): array => [
                $level['level'],
                $level['children'],
                $level['regular'],
                $level['at_risk'],
                $level['blocked'],
                self::percent($level['attendance_rate']),
            ], $summary['by_level']),
            percentColumns: [5],
        );
        $this->blank();

        $this->table(
            'Desglose por fin de semana',
            ['Fin de semana', 'Fechas', 'Presentes', 'Faltas', 'Justificadas', 'Evaluados', '% asistencia'],
            array_map(fn (array $weekend): array => [
                $weekend['name'] ?: 'Fin de semana',
                $weekend['label'].' '.substr($weekend['starts_at'], 0, 4),
                $weekend['present'],
                $weekend['absent'],
                $weekend['justified'],
                $weekend['applicable'],
                self::percent($weekend['attendance_rate']),
            ], $summary['by_weekend']),
            percentColumns: [6],
        );
        $this->blank();

        $this->legend();
    }

    /**
     * @param  array<int, array{0: string, 1: mixed}>  $pairs
     */
    private function keyValueBlock(string $heading, array $pairs): void
    {
        $this->mergedRow($heading, $this->styles->sectionHeader);

        foreach ($pairs as [$label, $value]) {
            $this->rowNumber++;
            $this->options->mergeCells(1, $this->rowNumber, self::SUMMARY_COLUMNS - 1, $this->rowNumber, $this->sheetIndex);
            $this->writer->addRow(new Row([
                Cell::fromValue($label, $this->styles->label),
                Cell::fromValue($value, $this->styles->bodyLeft),
            ]));
        }
    }

    private function kpiBlock(array $summary): void
    {
        $this->mergedRow('Indicadores generales', $this->styles->sectionHeader);

        $kpis = [
            ['Niños evaluados', $summary['children'], $this->styles->bodyCenter],
            ['Regulares', $summary['regular'], $this->styles->statuses[AttendanceReportService::STATUS_REGULAR]],
            ['En riesgo (a una falta del límite)', $summary['at_risk'], $this->styles->statuses[AttendanceReportService::STATUS_AT_RISK]],
            ['Sin derecho (límite alcanzado)', $summary['blocked'], $this->styles->statuses[AttendanceReportService::STATUS_BLOCKED]],
            ['Asistencias registradas', $summary['present'], $this->styles->bodyCenter],
            ['Faltas (ningún día del fin de semana)', $summary['absences'], $this->styles->bodyCenter],
            ['Faltas justificadas', $summary['justified'], $this->styles->bodyCenter],
            ['% de asistencia global', self::percent($summary['attendance_rate']), $this->styles->bodyPercent],
        ];

        foreach ($kpis as [$label, $value, $style]) {
            $this->rowNumber++;
            $this->writer->addRow(new Row([
                Cell::fromValue($label, $this->styles->label),
                Cell::fromValue($value, $style),
            ]));
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, int>  $percentColumns  Índices (0-based) con formato de porcentaje.
     */
    private function table(string $heading, array $headers, array $rows, array $percentColumns = []): void
    {
        $columns = count($headers);

        $this->mergedRow($heading, $this->styles->sectionHeader, lastColumn: $columns - 1);

        $this->rowNumber++;
        $this->writer->addRow(Row::fromValuesWithStyle($headers, $this->styles->tableHeaderSky));

        if ($rows === []) {
            $this->mergedRow('Sin datos para mostrar.', $this->styles->muted, lastColumn: $columns - 1);

            return;
        }

        foreach ($rows as $values) {
            $cells = [];

            foreach ($values as $index => $value) {
                $style = match (true) {
                    in_array($index, $percentColumns, true) => $this->styles->bodyPercent,
                    $index === 0 => $this->styles->body,
                    default => $this->styles->bodyCenter,
                };
                $cells[] = Cell::fromValue($value, $style);
            }

            $this->rowNumber++;
            $this->writer->addRow(new Row($cells));
        }
    }

    private function legend(): void
    {
        $this->mergedRow('Leyenda de la hoja Asistencias', $this->styles->sectionHeader);

        foreach (AttendanceReportStyles::CELL_LEGEND as $code => $description) {
            $this->legendRow($code, $description, $this->styles->legendKeys[$code]);
        }

        foreach (AttendanceReportService::STATUS_LABELS as $status => $label) {
            $this->legendRow($label, AttendanceReportStyles::STATUS_LEGEND[$status], $this->styles->legendKeys[$status]);
        }
    }

    private function legendRow(string $key, string $description, Style $keyStyle): void
    {
        $this->rowNumber++;
        $this->options->mergeCells(1, $this->rowNumber, self::SUMMARY_COLUMNS - 1, $this->rowNumber, $this->sheetIndex);
        $this->writer->addRow(new Row([
            Cell::fromValue($key, $keyStyle),
            Cell::fromValue($description, $this->styles->body),
        ]));
    }

    // ───────────────────────────── Hoja Asistencias ─────────────────────────────

    private function writeDetail(array $report): void
    {
        $weekends = $report['weekends'];
        $rows = $report['rows'];
        $fixedCount = count(self::DETAIL_FIXED_HEADERS);
        $weekendCount = count($weekends);
        $totalCount = count(self::DETAIL_TOTAL_HEADERS);
        $lastColumn = $fixedCount + $weekendCount + $totalCount - 1;

        $sheet = $this->writer->getCurrentSheet();
        $sheet->setName('Asistencias');
        $sheet->setSheetView(
            (new SheetView)
                ->withFreezeRow(self::DETAIL_HEADER_ROW + 1)
                ->withFreezeColumn(self::columnLetter($fixedCount)),
        );

        // La columna «#» solo necesita el ancho del número; las de texto van amplias.
        foreach ([5, 34, 52, 30, 36] as $index => $width) {
            $this->options->setColumnWidth($width, $index + 1);
        }
        if ($weekendCount > 0) {
            $this->options->setColumnWidthForRange(15, $fixedCount + 1, $fixedCount + $weekendCount);
        }
        foreach ([14, 12, 15, 15, 18, 16] as $index => $width) {
            $this->options->setColumnWidth($width, $fixedCount + $weekendCount + $index + 1);
        }

        $this->mergedRow('Reporte de asistencias — '.$report['church']['name'], $this->styles->title, lastColumn: $lastColumn, height: 28);
        $this->mergedRow(sprintf(
            'Periodo: %s   ·   Límite de faltas: %d   ·   Niños: %d   ·   Fines de semana: %d   ·   Generado: %s',
            $report['period_label'],
            $report['absence_limit'],
            count($rows),
            $weekendCount,
            $report['generated_at']->format('d/m/Y'),
        ), $this->styles->muted, lastColumn: $lastColumn);

        $headerCells = [];
        foreach (self::DETAIL_FIXED_HEADERS as $header) {
            $headerCells[] = Cell::fromValue($header, $this->styles->tableHeader);
        }
        foreach ($weekends as $weekend) {
            $headerCells[] = Cell::fromValue(
                $weekend['label']."\n".($weekend['name'] ?: substr($weekend['starts_at'], 0, 4)),
                $this->styles->tableHeaderSky,
            );
        }
        foreach (self::DETAIL_TOTAL_HEADERS as $header) {
            $headerCells[] = Cell::fromValue($header, $this->styles->tableHeader);
        }
        $this->rowNumber++;
        $this->writer->addRow(new Row($headerCells, height: 36));

        if ($rows === []) {
            $this->mergedRow('No hay niños que coincidan con los filtros seleccionados.', $this->styles->muted, lastColumn: $lastColumn);

            return;
        }

        $styles = $this->styles;

        foreach ($rows as $index => $row) {
            $statusStyle = $styles->statuses[$row['status']];
            $isRegular = $row['status'] === AttendanceReportService::STATUS_REGULAR;

            $cells = [
                Cell::fromValue($index + 1, $styles->bodyCenter),
                Cell::fromValue($row['code'], $styles->body),
                Cell::fromValue($row['name'], $styles->body),
                Cell::fromValue($row['level'], $styles->body),
                Cell::fromValue($row['community'], $styles->body),
            ];

            $codes = $row['codes'];
            $length = strlen($codes);

            for ($i = 0; $i < $length; $i++) {
                $code = $codes[$i];
                $comment = null;

                if (isset($row['details'][$i]) && $code !== AttendanceReportService::CELL_NOT_APPLICABLE) {
                    $prefix = $code === AttendanceReportService::CELL_JUSTIFIED ? 'Justificada: ' : 'Asistió a ';
                    $comment = new Comment(textRuns: [new TextRun($prefix.$row['details'][$i])]);
                }

                $cells[] = Cell::fromValue($code, $styles->cells[$code], $comment);
            }

            $cells[] = Cell::fromValue($row['present'], $styles->bodyCenter);
            $cells[] = Cell::fromValue($row['absences'], $isRegular ? $styles->bodyCenter : $statusStyle);
            $cells[] = Cell::fromValue($row['justified'], $styles->bodyCenter);
            $cells[] = Cell::fromValue(self::percent($row['attendance_rate']), $styles->bodyPercent);
            $cells[] = Cell::fromValue($row['remaining'], $styles->bodyCenter);
            $cells[] = Cell::fromValue(AttendanceReportService::STATUS_LABELS[$row['status']], $styles->statusesBold[$row['status']]);

            $this->rowNumber++;
            $this->writer->addRow(new Row($cells, height: 20));
        }

        $sheet->setAutoFilter(new AutoFilter(0, self::DETAIL_HEADER_ROW, $lastColumn, $this->rowNumber));
    }

    // ───────────────────────────── Utilidades ─────────────────────────────

    private function mergedRow(string $text, Style $style, ?int $lastColumn = null, float $height = Row::DEFAULT_HEIGHT): void
    {
        $this->rowNumber++;
        $this->options->mergeCells(0, $this->rowNumber, $lastColumn ?? self::SUMMARY_COLUMNS - 1, $this->rowNumber, $this->sheetIndex);
        $this->writer->addRow(new Row([Cell::fromValue($text, $style)], height: $height));
    }

    private function blank(): void
    {
        $this->rowNumber++;
        $this->writer->addRow(new Row([]));
    }

    private static function percent(?float $rate): ?float
    {
        return $rate === null ? null : $rate / 100;
    }

    /** Letra de la columna a partir de su índice 0-based (0 = A). */
    private static function columnLetter(int $index): string
    {
        $letter = '';
        $index++;

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }
}
