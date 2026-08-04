<?php

namespace App\Repositories\Catechism;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\External\ExternalChild;
use App\Models\External\ExternalCommunity;
use App\Models\External\ExternalLevel;
use App\Models\ExternalChildImport;
use App\Models\Operation\Level;
use App\Models\Regions\Community;
use App\Models\Regions\Municipality;
use App\Models\User;
use App\Services\UserScopeService;
use App\Support\HostingerCache;
use App\Support\SplitLastNames;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ExternosRepository
{
    public function paginateWithFilters(
        User $user,
        string $search,
        ?int $levelId = null,
        ?int $communityId = null
    ): LengthAwarePaginator {
        $filtered = $this->applyFilters($this->allChildren($user), $search, $levelId, $communityId);

        $importedSet = array_fill_keys(
            ExternalChildImport::query()
                ->whereIn('external_child_id', $filtered->pluck('id'))
                ->pluck('external_child_id')
                ->all(),
            true
        );

        $items = $filtered
            ->map(fn (array $child): array => [
                ...$child,
                'imported' => isset($importedSet[$child['id']]),
            ])
            ->all();

        $perPage = 15;
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $total = count($items);

        return (new LengthAwarePaginator(
            array_slice($items, ($page - 1) * $perPage, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        ))->withQueryString();
    }

    public function externosForBulkImport(
        User $user,
        string $search,
        ?int $levelId = null,
        ?int $communityId = null
    ): Collection {
        return $this->applyFilters($this->allChildren($user), $search, $levelId, $communityId)
            ->pluck('id');
    }

    public function allChildren(User $user): Collection
    {
        $key = $this->allKey($user);

        $data = Cache::tags(HostingerCache::tags())->remember(
            $key,
            HostingerCache::ttl('all'),
            fn () => $this->loadAllChildren($user)
        );

        return collect($data);
    }

    private function loadAllChildren(User $user): array
    {
        $scope = new UserScopeService($user);

        $query = ExternalChild::query()
            ->with(['community:id,name', 'church:id,name', 'childPeriods.levels.level:id,name'])
            ->orderBy('name')
            ->orderBy('last_names');

        return ($scope->isGlobal() ? $query : $scope->applyChildScope($query))
            ->get()
            ->map(fn (ExternalChild $child): array => $this->serializeExternalChild($child))
            ->all();
    }

    private function applyFilters(
        Collection $children,
        string $search,
        ?int $levelId,
        ?int $communityId
    ): Collection {
        return $children
            ->filter(function (array $child) use ($search, $levelId, $communityId): bool {
                if ($communityId !== null && (int) $child['community_id'] !== $communityId) {
                    return false;
                }

                if ($levelId !== null) {
                    $hasLevel = collect($child['levels'])
                        ->contains(fn (array $level): bool => $level['level_id'] === $levelId);

                    if (! $hasLevel) {
                        return false;
                    }
                }

                if ($search !== '') {
                    $needle = mb_strtolower($search);
                    $haystack = implode(' ', [
                        (string) $child['full_name'],
                        (string) $child['email'],
                        (string) $child['phone'],
                    ]);

                    if (! str_contains(mb_strtolower($haystack), $needle)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    public function findOrFail(int $id): ExternalChild
    {
        return ExternalChild::query()
            ->with(['community:id,name', 'church:id,name', 'childPeriods.levels.level:id,name'])
            ->findOrFail($id);
    }

    public function getFilterOptions(User $user): array
    {
        $key = $this->filtersKey($user);

        return Cache::tags(HostingerCache::tags())->remember(
            $key,
            HostingerCache::ttl('filters'),
            fn () => $this->loadFilterOptions($user)
        );
    }

    private function loadFilterOptions(User $user): array
    {
        $scope = new UserScopeService($user);

        return [
            'communities' => ExternalCommunity::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('church_id', $scope->churchIds()))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ExternalCommunity $community): array => [
                    'id' => $community->id,
                    'name' => $community->name,
                ])
                ->all(),
            'levels' => ExternalLevel::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('church_id', $scope->churchIds()))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ExternalLevel $level): array => [
                    'id' => $level->id,
                    'name' => $level->name,
                ])
                ->all(),
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

    private function scopeKey(User $user): string
    {
        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return 'global';
        }

        return 'churches:'.md5(collect($scope->churchIds())->sort()->implode('-'));
    }

    private function filtersKey(User $user): string
    {
        return config('hostinger_cache.prefix').':filters:'.$this->scopeKey($user);
    }

    private function allKey(User $user): string
    {
        return config('hostinger_cache.prefix').':all:'.$this->scopeKey($user);
    }
}
