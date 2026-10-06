<?php

namespace App\Exports\Catechism;

use App\Globals\Status;
use App\Models\Operation\Level;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChildImportLevelsSheet implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function title(): string
    {
        return 'niveles';
    }

    public function query()
    {
        return Level::query()
            ->with('diocese:id,name')
            ->where('status', Status::ACTIVE)
            ->orderBy('diocese_id')
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['nivel', 'nombre', 'diocese_id', 'diócesis'];
    }

    public function map($level): array
    {
        return [
            $level->id,
            $level->name,
            $level->diocese_id,
            $level->diocese?->name,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return ExcelHeaderStyle::apply($sheet, 'A1:D1');
    }
}
