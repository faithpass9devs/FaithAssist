<?php

namespace App\Repositories\Operation;

use App\Models\Ecclesiastes\Church;
use App\Models\Lada;
use App\Models\Operation\Staff;
use App\Models\Operation\StaffType;
use App\Models\Regions\Community;
use App\Models\Regions\Municipality;
use App\Models\User;
use App\Services\UserScopeService;

class StaffRepository
{
    public function paginateWithFilters(
        User $user,
        string $search,
        ?int $churchId = null,
        ?int $municipalityId = null,
        ?int $communityId = null,
        ?int $staffTypeId = null
    ) {
        $scope = new UserScopeService($user);

        $query = Staff::query()
            ->with(['staffType:id,name', 'church:id,name', 'community:id,name'])
            ->when($search !== '', fn ($builder) => $builder->search($search))
            ->when($churchId, fn ($builder) => $builder->where('church_id', $churchId))
            ->when($communityId, fn ($builder) => $builder->where('community_id', $communityId))
            ->when($staffTypeId, fn ($builder) => $builder->where('staff_type_id', $staffTypeId))
            ->when($municipalityId, function ($builder) use ($municipalityId) {
                $builder->where(function ($nested) use ($municipalityId) {
                    $nested->whereHas('church', fn ($church) => $church->where('municipality_id', $municipalityId))
                        ->orWhereHas('community', fn ($community) => $community->where('municipality_id', $municipalityId));
                });
            })
            ->orderBy('paterno')
            ->orderBy('materno')
            ->orderBy('name');

        return ($scope->isGlobal() ? $query : $scope->applyChildScope($query))
            ->paginate(15)
            ->withQueryString();
    }

    public function create(array $data): Staff
    {
        return Staff::create($data);
    }

    public function update(Staff $staff, array $data): Staff
    {
        $staff->update($data);

        return $staff->fresh(['staffType:id,name', 'church:id,name', 'community:id,name']);
    }

    public function delete(Staff $staff): void
    {
        $staff->delete();
    }

    public function getFilterOptions(User $user): array
    {
        return $this->options($user);
    }

    public function getFormOptions(User $user): array
    {
        return $this->options($user);
    }

    private function options(User $user): array
    {
        $scope = new UserScopeService($user);

        return [
            'staffTypes' => StaffType::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'churches' => Church::query()
                ->with('deanery:id,diocese_id')
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->churchIds()))
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'deanery_id', 'municipality_id', 'name'])
                ->map(fn (Church $church): array => [
                    'id' => $church->id,
                    'municipality_id' => $church->municipality_id,
                    'diocese_id' => $church->deanery?->diocese_id,
                    'name' => $church->name,
                ])
                ->values(),
            'municipalities' => Municipality::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->municipalityIds()))
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'communities' => Community::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->communityIds()))
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'municipality_id', 'name']),
        ];
    }

    public function serializeStaff(Staff $staff): array
    {
        return [
            'id' => $staff->id,
            'staff_type_id' => $staff->staff_type_id,
            'church_id' => $staff->church_id,
            'community_id' => $staff->community_id,
            'name' => $staff->name,
            'paterno' => $staff->paterno,
            'materno' => $staff->materno,
            'full_name' => trim(collect([$staff->name, $staff->paterno, $staff->materno])->filter()->implode(' ')),
            'phone_lada' => $staff->phone_lada,
            'phone' => $staff->phone,
            'birthdate' => $staff->birthdate?->format('Y-m-d'),
            'code' => $staff->code,
            'staff_type' => $staff->staffType?->name,
            'church' => $staff->church?->name,
            'community' => $staff->community?->name,
            'created_at' => $staff->created_at?->format('d/m/Y'),
        ];
    }

    public function getLadaOptions(): array
    {
        return Lada::options();
    }

    public function getDefaultLada(): string
    {
        return Lada::defaultCode();
    }
}
