<?php

namespace App\Exports\Catechism;

use App\Globals\Status;
use App\Models\Regions\Community;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChildImportCommunitiesSheet implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function title(): string
    {
        return 'comunidades';
    }

    public function query()
    {
        return Community::query()
            ->with('municipality:id,name')
            ->where('status', Status::ACTIVE)
            ->orderBy('municipality_id')
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['comunidad', 'nombre', 'municipio_id', 'municipio'];
    }

    public function map($community): array
    {
        return [
            $community->id,
            $community->name,
            $community->municipality_id,
            $community->municipality?->name,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return ExcelHeaderStyle::apply($sheet, 'A1:D1');
    }
}
