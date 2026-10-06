<?php

namespace App\Exports\Catechism;

use App\Models\Catechism\Child;
use App\Models\Catechism\ChildLevelAssignment;
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

class ChildrenExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly User $user,
        private readonly array $filters = []
    ) {
    }

    public function query(): Builder
    {
        $search = (string) ($this->filters['search'] ?? '');
        $churchId = $this->filters['church_id'] ?? null;
        $municipalityId = $this->filters['municipality_id'] ?? null;
        $communityId = $this->filters['community_id'] ?? null;
        $levelId = $this->filters['level_id'] ?? null;
        $status = $this->filters['status'] ?? null;

        $scope = new UserScopeService($this->user);

        $query = Child::query()
            ->with(['church:id,name', 'community:id,name', 'activeLevelAssignments.level:id,name'])
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->when($churchId, fn (Builder $query) => $query->where('church_id', $churchId))
            ->when($communityId, fn (Builder $query) => $query->where('community_id', $communityId))
            ->when($levelId, fn (Builder $query) => $query->whereHas(
                'activeLevelAssignments',
                fn (Builder $assignment) => $assignment->where('level_id', $levelId)
            ))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($municipalityId, function (Builder $query) use ($municipalityId): void {
                $query->where(function (Builder $builder) use ($municipalityId): void {
                    $builder->whereHas('church', fn (Builder $church) => $church->where('municipality_id', $municipalityId))
                        ->orWhereHas('community', fn (Builder $community) => $community->where('municipality_id', $municipalityId));
                });
            })
            ->orderBy('paterno')
            ->orderBy('materno')
            ->orderBy('name');

        return $scope->isGlobal() ? $query : $scope->applyChildScope($query);
    }

    public function headings(): array
    {
        return ['Código', 'Nombre', 'Iglesia', 'Niveles', 'Comunidad', 'Nacimiento', 'Estado'];
    }

    public function map($child): array
    {
        return [
            $child->code,
            trim(collect([$child->name, $child->paterno, $child->materno])->filter()->implode(' ')),
            $child->church?->name ?? '',
            $child->activeLevelAssignments
                ->map(fn (ChildLevelAssignment $assignment): ?string => $assignment->level?->name)
                ->filter()
                ->values()
                ->implode(', '),
            $child->community?->name ?? '',
            $child->birthdate?->format('Y-m-d') ?? '',
            $this->statusLabel((string) $child->status),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:G1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF0F172A');
        $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'completed' => 'Completado',
            'withdraw' => 'Retirado',
            'suspended' => 'Suspendido',
            default => $status,
        };
    }
}
