<?php

namespace App\Exports\Regions;

use App\Models\Regions\Municipality;
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

class MunicipalitiesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly User $user,
        private readonly string $search = ''
    ) {
    }

    public function query(): Builder
    {
        $scope = new UserScopeService($this->user);

        return Municipality::query()
            ->with(['state:id,name', 'diocese:id,name'])
            ->when(! $scope->isGlobal(), fn (Builder $query) => $query->whereIn('id', $scope->municipalityIds()))
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$this->search}%"))
            ->select(['id', 'state_id', 'diocese_id', 'name', 'status'])
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['Estado', 'Diócesis', 'Nombre', 'Estatus'];
    }

    public function map($municipality): array
    {
        return [
            $municipality->state?->name ?? '',
            $municipality->diocese?->name ?? '',
            $municipality->name,
            $municipality->status === 'active' ? 'Activo' : 'Inactivo',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:D1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:D1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF0F172A');
        $sheet->getStyle('A1:D1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }
}
