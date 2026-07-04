<?php

namespace App\Exports\Regions;

use App\Models\Regions\State;
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

class StatesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly User $user,
        private readonly string $search = ''
    ) {
    }

    public function query(): Builder
    {
        $scope = new UserScopeService($this->user);

        return State::query()
            ->when(! $scope->isGlobal(), fn (Builder $query) => $query->whereIn('id', $scope->stateIds()))
            ->when($this->search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$this->search}%"))
            ->select(['id', 'name', 'short_name', 'status'])
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['Nombre', 'Abreviatura', 'Estatus'];
    }

    public function map($state): array
    {
        return [
            $state->name,
            $state->short_name,
            $state->status === 'active' ? 'Activo' : 'Inactivo',
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
