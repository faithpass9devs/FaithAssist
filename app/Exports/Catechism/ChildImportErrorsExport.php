<?php

namespace App\Exports\Catechism;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChildImportErrorsExport implements FromArray, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * @param  array<int, array{line: int|string, message: string}>  $errors
     */
    public function __construct(private readonly array $errors)
    {
    }

    public function array(): array
    {
        return array_map(fn (array $error): array => [$error['line'], $error['message']], $this->errors);
    }

    public function headings(): array
    {
        return ['Fila', 'Motivo'];
    }

    public function map($row): array
    {
        return [(int) $row[0], (string) $row[1]];
    }

    public function styles(Worksheet $sheet): array
    {
        return ExcelHeaderStyle::apply($sheet, 'A1:B1');
    }
}
