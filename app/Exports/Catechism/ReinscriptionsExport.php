<?php

namespace App\Exports\Catechism;

use App\Globals\Status;
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

class ReinscriptionsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly User $user,
        private readonly array $filters = []
    ) {
    }

    public function query(): Builder
    {
        $search = (string) ($this->filters['search'] ?? '');
        $communityId = $this->filters['community_id'] ?? null;
        $levelId = $this->filters['level_id'] ?? null;

        $scope = new UserScopeService($this->user);

        $query = Child::query()
            ->with([
                'church:id,name,deanery_id',
                'community:id,name',
                'activeLevelAssignments.level:id,name,diocese_id',
            ])
            ->where('status', Status::ACTIVE)
            ->whereHas('activeLevelAssignments')
            ->whereDoesntHave('reinscriptions', fn (Builder $q) => $q->whereHas('period', fn (Builder $p) => $p->where('status', Status::IN_PROGRESS)))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('paterno', 'like', "%{$search}%")
                        ->orWhere('materno', 'like', "%{$search}%")
                        ->orWhereHas('church', fn (Builder $church) => $church->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($communityId, fn (Builder $query) => $query->where('community_id', $communityId))
            ->when($levelId, fn (Builder $query) => $query->whereHas(
                'activeLevelAssignments',
                fn (Builder $assignment) => $assignment->where('level_id', $levelId)
            ))
            ->orderBy('paterno')
            ->orderBy('materno')
            ->orderBy('name');

        return $scope->isGlobal() ? $query : $scope->applyChildScope($query);
    }

    public function headings(): array
    {
        return ['Código', 'Niño', 'Parroquia', 'Comunidad', 'Niveles actuales'];
    }

    public function map($child): array
    {
        return [
            $child->code,
            trim(collect([$child->name, $child->paterno, $child->materno])->filter()->implode(' ')),
            $child->church?->name ?? '',
            $child->community?->name ?? '',
            $child->activeLevelAssignments
                ->map(fn (ChildLevelAssignment $assignment): ?string => $assignment->level?->name)
                ->filter()
                ->values()
                ->implode(', '),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:E1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:E1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF0F172A');
        $sheet->getStyle('A1:E1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }
}
