<?php

namespace App\Repositories\Catechism;

use App\Models\External\ExternalChild;
use App\Models\External\ExternalCommunity;
use App\Models\External\ExternalLevel;
use App\Models\User;
use App\Services\UserScopeService;

class ExternosRepository
{
    public function paginateWithFilters(
        User $user,
        string $search,
        ?int $levelId = null,
        ?int $communityId = null
    ) {
        $scope = new UserScopeService($user);

        $query = ExternalChild::query()
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
            ))
            ->orderBy('name')
            ->orderBy('last_names');

        return ($scope->isGlobal() ? $query : $scope->applyChildScope($query))
            ->paginate(15)
            ->withQueryString();
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

    public function serializeExternalChild(ExternalChild $child): array
    {
        return [
            'id' => $child->id,
            'full_name' => trim(collect([$child->name, $child->last_names])->filter()->implode(' ')),
            'birthdate' => $child->birthdate?->format('Y-m-d'),
            'sex' => $child->sex,
            'email' => $child->email,
            'phone' => $child->phone,
            'emergency_phone' => $child->emergency_phone,
            'blood_type' => $child->blood_type,
            'notes' => $child->notes,
            'status' => $child->status,
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
