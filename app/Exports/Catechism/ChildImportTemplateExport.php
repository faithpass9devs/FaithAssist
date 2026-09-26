<?php

namespace App\Exports\Catechism;

use App\Imports\Catechism\ChildrenImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChildImportTemplateExport implements FromArray, WithHeadings, WithMapping, WithMultipleSheets, WithStyles, ShouldAutoSize, WithTitle
{
    /**
     * Fila de ejemplo. Sustituir o borrar antes de cargar el archivo real.
     */
    public const EXAMPLE_ROW = [
        'JUAN CARLOS',
        'PÉREZ LÓPEZ',
        'GARCÍA SÁNCHEZ',
        '2015-03-14',
        'M',
        'O+',
        '1',
        '1,2',
        'juan.carlos@correo.com',
        '5512345678',
        '5598765432',
    ];

    public function title(): string
    {
        return 'ninos';
    }

    public function array(): array
    {
        return [self::EXAMPLE_ROW];
    }

    public function headings(): array
    {
        return ChildrenImport::HEADINGS;
    }

    public function map($row): array
    {
        return (array) $row;
    }

    public function sheets(): array
    {
        return [
            $this,
            new ChildImportCommunitiesSheet,
            new ChildImportLevelsSheet,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return ExcelHeaderStyle::apply($sheet, 'A1:K1');
    }
}
