<?php

namespace App\Services;

use App\Models\Ecclesiastes\Chapel;
use App\Models\Ecclesiastes\Church;
use App\Models\Ecclesiastes\Deanery;
use App\Models\Ecclesiastes\Diocese;
use App\Models\Regions\Community;
use App\Models\Regions\Municipality;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Centralizes data-visibility scope rules for a given user.
 *
 * Scope levels (derived from users.diocese_id / deanery_id / church_id / chapel_id):
 *   global   → null diocese_id — sees everything
 *   diocese  → diocese_id set, deanery_id null, church_id null
 *   deanery  → diocese_id + deanery_id set, church_id null
 *   church   → diocese_id + deanery_id + church_id set, chapel_id null
 *   chapel   → diocese_id + deanery_id + church_id + chapel_id set
 *
 * Returning an *empty* collection from an `*Ids()` method signals "no filter needed"
 * (i.e. the caller should skip the whereIn). Check isGlobal() first.
 */
class UserScopeService
{
    public function __construct(private readonly User $user) {}

    /** True when the user is a global admin (no scope restrictions). */
    public function isGlobal(): bool
    {
        return $this->user->diocese_id === null
            && $this->user->deanery_id === null
            && $this->user->church_id === null
            && $this->user->chapel_id === null;
    }

    /** Returns 'global' | 'diocese' | 'deanery' | 'church' | 'chapel'. */
    public function level(): string
    {
        if ($this->isGlobal()) {
            return 'global';
        }

        if ($this->user->chapel_id !== null) {
            return 'chapel';
        }

        if ($this->user->church_id !== null) {
            return 'church';
        }

        if ($this->user->deanery_id !== null) {
            return 'deanery';
        }

        return 'diocese';
    }

    /**
     * IDs of dioceses this user can see.
     * Empty = no filter (isGlobal() is true).
     */
    public function dioceseIds(): Collection
    {
        if ($this->isGlobal()) {
            return collect();
        }

        return collect([$this->user->diocese_id]);
    }

    /**
     * IDs of states this user can see (derived from the diocese's state).
     */
    public function stateIds(): Collection
    {
        if ($this->isGlobal()) {
            return collect();
        }

        $stateId = Diocese::find($this->user->diocese_id)?->state_id;

        return $stateId ? collect([$stateId]) : collect();
    }

    /**
     * IDs of deaneries this user can see.
     */
    public function deaneryIds(): Collection
    {
        if ($this->isGlobal()) {
            return collect();
        }

        if ($this->user->deanery_id !== null) {
            return collect([$this->user->deanery_id]);
        }

        // Diocese scope: all deaneries of the diocese
        return Deanery::where('diocese_id', $this->user->diocese_id)->pluck('id');
    }

    /**
     * IDs of churches this user can see.
     */
    public function churchIds(): Collection
    {
        if ($this->isGlobal()) {
            return collect();
        }

        if ($this->user->church_id !== null) {
            return collect([$this->user->church_id]);
        }

        // Restrict to deanery or diocese
        return Church::query()
            ->when(
                $this->user->deanery_id !== null,
                fn ($q) => $q->where('deanery_id', $this->user->deanery_id),
                fn ($q) => $q->whereHas(
                    'deanery',
                    fn ($d) => $d->where('diocese_id', $this->user->diocese_id)
                )
            )
            ->pluck('id');
    }

    /**
     * IDs of chapels this user can see.
     * Empty = no filter (isGlobal() is true).
     */
    public function chapelIds(): Collection
    {
        if ($this->isGlobal()) {
            return collect();
        }

        if ($this->user->chapel_id !== null) {
            return collect([$this->user->chapel_id]);
        }

        $allowedChurchIds = $this->churchIds();

        if ($allowedChurchIds->isEmpty()) {
            return collect();
        }

        return Chapel::whereIn('church_id', $allowedChurchIds)->pluck('id');
    }

    /**
     * IDs of municipalities this user can see.
     */
    public function municipalityIds(): Collection
    {
        if ($this->isGlobal()) {
            return collect();
        }

        if ($this->user->church_id !== null) {
            $municipalityId = Church::find($this->user->church_id)?->municipality_id;

            return $municipalityId ? collect([$municipalityId]) : collect();
        }

        if ($this->user->deanery_id !== null) {
            // Municipalities of churches within the deanery
            return Church::where('deanery_id', $this->user->deanery_id)
                ->whereNotNull('municipality_id')
                ->distinct()
                ->pluck('municipality_id');
        }

        // Diocese scope: all municipalities of the diocese
        return Municipality::where('diocese_id', $this->user->diocese_id)->pluck('id');
    }

    /**
     * IDs of communities this user can see (derived from allowed municipalities).
     */
    public function communityIds(): Collection
    {
        if ($this->isGlobal()) {
            return collect();
        }

        $allowedMunicipalityIds = $this->municipalityIds();

        if ($allowedMunicipalityIds->isEmpty()) {
            return collect();
        }

        return Community::whereIn('municipality_id', $allowedMunicipalityIds)->pluck('id');
    }

    /**
     * Apply chapel visibility filter to an existing query builder.
     * Chapels are visible when their church OR community is in scope.
     */
    public function applyChapelScope(Builder $query): Builder
    {
        if ($this->isGlobal()) {
            return $query;
        }

        if ($this->user->chapel_id !== null) {
            return $query->whereKey($this->user->chapel_id);
        }

        $allowedChurchIds = $this->churchIds();
        $allowedCommunityIds = $this->communityIds();

        return $query->where(function (Builder $scope) use ($allowedChurchIds, $allowedCommunityIds): void {
            $hasChurches = $allowedChurchIds->isNotEmpty();
            $hasCommunities = $allowedCommunityIds->isNotEmpty();

            if ($hasChurches) {
                $scope->whereIn('church_id', $allowedChurchIds);
            }

            if ($hasCommunities) {
                $method = $hasChurches ? 'orWhereIn' : 'whereIn';
                $scope->{$method}('community_id', $allowedCommunityIds);
            }

            if (! $hasChurches && ! $hasCommunities) {
                $scope->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Apply child visibility filter to an existing query builder.
     * Children are visible when their church OR community is in scope.
     */
    public function applyChildScope(Builder $query): Builder
    {
        if ($this->isGlobal()) {
            return $query;
        }

        $allowedChurchIds = $this->churchIds();
        $allowedCommunityIds = $this->communityIds();

        return $query->where(function (Builder $scope) use ($allowedChurchIds, $allowedCommunityIds): void {
            $hasChurches = $allowedChurchIds->isNotEmpty();
            $hasCommunities = $allowedCommunityIds->isNotEmpty();

            if ($hasChurches) {
                $scope->whereIn('church_id', $allowedChurchIds);
            }

            if ($hasCommunities) {
                $method = $hasChurches ? 'orWhereIn' : 'whereIn';
                $scope->{$method}('community_id', $allowedCommunityIds);
            }

            if (! $hasChurches && ! $hasCommunities) {
                $scope->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Apply weekend visibility. Weekends belong to a church; chapel users see
     * the weekends of their parent church but cannot manage them by policy.
     */
    public function applyWeekendScope(Builder $query): Builder
    {
        if ($this->isGlobal()) {
            return $query;
        }

        $allowedChurchIds = $this->churchIds();

        return $allowedChurchIds->isEmpty()
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('church_id', $allowedChurchIds);
    }

    /**
     * Apply mass visibility. Chapel users are restricted to their chapel's
     * masses; broader users see all masses in their visible churches.
     */
    public function applyMassScope(Builder $query): Builder
    {
        if ($this->isGlobal()) {
            return $query;
        }

        if ($this->user->chapel_id !== null) {
            return $query->where('chapel_id', $this->user->chapel_id);
        }

        $allowedChurchIds = $this->churchIds();

        return $allowedChurchIds->isEmpty()
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('church_id', $allowedChurchIds);
    }

    /**
     * Apply attendance incident visibility through the incident's weekend.
     */
    public function applyMassAttendanceIncidentScope(Builder $query): Builder
    {
        if ($this->isGlobal()) {
            return $query;
        }

        $allowedChurchIds = $this->churchIds();

        return $allowedChurchIds->isEmpty()
            ? $query->whereRaw('1 = 0')
            : $query->whereHas(
                'weekend',
                fn (Builder $weekend) => $weekend->whereIn('church_id', $allowedChurchIds)
            );
    }
}
