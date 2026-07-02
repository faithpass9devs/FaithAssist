<?php

namespace App\Exports\Regions;

use App\Models\Regions\Community;
use App\Models\User;
use App\Services\UserScopeService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CommunitiesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly User $user,
        private readonly string $search = ''
    ) {
    }

    public function query(): Builder
    {
        $scope = new UserScopeService($this->user);

        return Community::query()
            ->with(['municipality:id,name'])
            ->when(! $scope->isGlobal(), fn (Builder $query) => $query->whereIn('municipality_id', $scope->municipalityIds()))
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$this->search}%"))
            ->select(['id', 'municipality_id', 'name', 'status'])
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['Municipio', 'Nombre', 'Estatus'];
    }

    public function map($community): array
    {
        return [
            $community->municipality?->name ?? '',
            $community->name,
            $community->status === 'active' ? 'Activo' : 'Inactivo',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:C1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:C1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF0F172A');
        $sheet->getStyle('A1:C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }
}
