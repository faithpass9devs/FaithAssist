<?php

namespace App\Exports\Security;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class SessionHistoryExport extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithEvents
{
    public function __construct(
        private readonly array $user,
        private readonly string $period,
        private readonly Collection $rows,
    ) {}

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function array(): array
    {
        $lines = [
            ['Historial de actividad y sesiones'],
            ['Usuario', $this->user['name'], 'Correo', $this->user['email']],
            ['Teléfono', $this->user['phone'], 'Parroquia', $this->user['parish']],
            ['Rol', $this->user['role'], 'Estatus', $this->user['status']],
            ['Periodo', $this->period],
            ['Generado', now()->setTimezone(config('app.display_timezone'))->format('d/m/Y h:i A')],
            [' '],
            ['Inicio', 'Fin', 'Dispositivo y modelo', 'Navegador', 'Dirección IP', 'Ubicación'],
        ];

        foreach ($this->rows as $row) {
            $lines[] = array_values($row);
        }

        if ($this->rows->isEmpty()) {
            $lines[] = ['Sin accesos registrados en el periodo'];
        }

        return $lines;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = 8 + max(1, $this->rows->count());
                $sheetName = 'Historial - '.Str::slug($this->user['name'], ' ');
                $sheet->setTitle(mb_substr(trim($sheetName), 0, 31));
                $sheet->mergeCells('A1:F1');
                $sheet->getRowDimension(1)->setRowHeight(36);
                $sheet->getStyle('A1:F1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('164E63');
                $sheet->getStyle('A2:F6')->getFont()->setSize(11);
                $sheet->getStyle('A2:A6')->getFont()->setBold(true)->getColor()->setRGB('334155');
                $sheet->getStyle('C2:C4')->getFont()->setBold(true)->getColor()->setRGB('334155');
                $sheet->getStyle('A2:A6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
                $sheet->getStyle('C2:C4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
                $sheet->getStyle('A8:F8')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A8:F8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('334155');
                $sheet->getStyle('A8:F8')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A8:F{$lastRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("A8:F{$lastRow}")->getBorders()->getBottom()->setBorderStyle('hair');
                $sheet->getRowDimension(8)->setRowHeight(32);

                foreach (['A' => 26, 'B' => 24, 'C' => 38, 'D' => 26, 'E' => 26, 'F' => 68] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $sheet->setShowGridlines(false);
                $sheet->getTabColor()->setRGB('0284C7');
                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->getPageSetup()->setFitToPage(true);

                for ($row = 9; $row <= $lastRow; $row++) {
                    $values = $this->rows->get($row - 9);
                    $lines = $values ? substr_count($values[5], "\n") + 1 : 1;
                    $sheet->getRowDimension($row)->setRowHeight(min(400, max(48, $lines * 32)));
                    if ($row % 2 === 0) {
                        $sheet->getStyle("A{$row}:F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
                    }
                }

                $sheet->freezePane('A9');
                $sheet->setAutoFilter("A8:F{$lastRow}");
            },
        ];
    }
}
