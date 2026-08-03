<?php

namespace App\Repositories\Catechism;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\External\ExternalChild;
use App\Models\External\ExternalCommunity;
use App\Models\External\ExternalLevel;
use App\Models\Operation\Level;
use App\Models\Regions\Community;
use App\Models\Regions\Municipality;
use App\Models\User;
use App\Services\UserScopeService;
use App\Support\SplitLastNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ExternosRepository
{
    public function paginateWithFilters(
        User $user,
        string $search,
        ?int $levelId = null,
        ?int $communityId = null
    ) {
        $scope = new UserScopeService($user);

        $query = $this->baseQuery($search, $levelId, $communityId)
            ->orderBy('name')
            ->orderBy('last_names');

        return ($scope->isGlobal() ? $query : $scope->applyChildScope($query))
            ->paginate(15)
            ->withQueryString();
    }

    public function externosForBulkImport(
        User $user,
        string $search,
        ?int $levelId = null,
        ?int $communityId = null
    ): Collection {
        $scope = new UserScopeService($user);

        $query = $this->baseQuery($search, $levelId, $communityId);

        return ($scope->isGlobal() ? $query : $scope->applyChildScope($query))
            ->pluck('id');
    }

    private function baseQuery(
        string $search,
        ?int $levelId = null,
        ?int $communityId = null
    ): Builder {
        return ExternalChild::query()
            ->with(['community:id,name', 'church:id,name', 'childPeriods.levels.level:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('last_names', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($communityId, fn ($query) => $query->where('community_id', $communityId))
            ->when($levelId, fn ($query) => $query->whereHas(
                'childPeriods.levels',
                fn ($level) => $level->where('level_id', $levelId)
            ));
    }

    public function findOrFail(int $id): ExternalChild
    {
        return ExternalChild::query()
            ->with(['community:id,name', 'church:id,name', 'childPeriods.levels.level:id,name'])
            ->findOrFail($id);
    }

    public function getFilterOptions(User $user): array
    {
        $scope = new UserScopeService($user);

        return [
            'communities' => ExternalCommunity::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('church_id', $scope->churchIds()))
                ->orderBy('name')
                ->get(['id', 'name']),
            'levels' => ExternalLevel::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('church_id', $scope->churchIds()))
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    public function getLevels(User $user, ?int $churchId): array
    {
        $scope = new UserScopeService($user);

        $dioceseId = $churchId
            ? Church::query()->find($churchId)?->deanery?->diocese_id
            : null;

        return Level::query()
            ->when($dioceseId, fn ($q) => $q->where('diocese_id', $dioceseId))
            ->when(! $dioceseId && ! $scope->isGlobal(), fn ($q) => $q->whereIn('diocese_id', $scope->dioceseIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function getChurches(User $user): array
    {
        $scope = new UserScopeService($user);

        return Church::query()
            ->with('deanery:id,diocese_id')
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->churchIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'deanery_id', 'municipality_id', 'name'])
            ->map(fn (Church $church): array => [
                'id' => $church->id,
                'municipality_id' => $church->municipality_id,
                'diocese_id' => $church->deanery?->diocese_id,
                'name' => $church->name,
            ])
            ->values()
            ->all();
    }

    public function getMunicipalities(User $user): array
    {
        $scope = new UserScopeService($user);

        return Municipality::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->municipalityIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function getCommunities(User $user): array
    {
        $scope = new UserScopeService($user);

        return Community::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->communityIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'municipality_id', 'name'])
            ->toArray();
    }

    public function serializeExternalChild(ExternalChild $child, bool $imported = false): array
    {
        $lastNames = SplitLastNames::split((string) $child->last_names);

        return [
            'id' => $child->id,
            'church_id' => $child->church_id,
            'community_id' => $child->community_id,
            'full_name' => trim(collect([$child->name, $child->last_names])->filter()->implode(' ')),
            'name' => $child->name,
            'paterno' => $lastNames['paterno'],
            'materno' => $lastNames['materno'],
            'birthdate' => $child->birthdate?->format('Y-m-d'),
            'sex' => $child->sex,
            'email' => $child->email,
            'phone' => $child->phone,
            'emergency_phone' => $child->emergency_phone,
            'blood_type' => $child->blood_type,
            'notes' => $child->notes,
            'status' => $child->status,
            'imported' => $imported,
            'church' => $child->church?->name,
            'community' => $child->community?->name,
            'levels' => $child->childPeriods
                ->flatMap(fn ($period) => $period->levels->map(fn ($level) => [
                    'level_id' => $level->level?->id,
                    'name' => $level->level?->name,
                    'status' => $level->status,
                    'is_primary' => $level->is_primary,
                ]))
                ->filter(fn (array $level): bool => $level['level_id'] !== null)
                ->values()
                ->all(),
        ];
    }
}
