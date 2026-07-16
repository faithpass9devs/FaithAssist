<?php

namespace App\Repositories\Masses;

use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Masses\Mass;
use App\Models\Masses\Weekend;
use App\Models\Operation\Level;
use App\Models\Regions\Community;
use App\Models\Regions\Municipality;
use App\Models\User;
use App\Services\UserScopeService;

class ManualAttendanceRepository
{
    public function getChildren(
        User $user,
        string $code,
        string $name,
        string $levelName,
        ?int $municipalityId,
        ?int $communityId,
        int $limit = 20
    ): array {
        $scope = new UserScopeService($user);

        return ($scope->isGlobal() ? Child::query() : $scope->applyChildScope(Child::query()))
            ->with(['church:id,name', 'community:id,name', 'activeLevelAssignments.level:id,name'])
            ->where('status', Status::ACTIVE)
            ->when($code !== '', fn ($query) => $query->where('code', 'like', "%{$code}%"))
            ->when($name !== '', function ($query) use ($name): void {
                $query->where(function ($builder) use ($name): void {
                    $builder->where('name', 'like', "%{$name}%")
                        ->orWhere('paterno', 'like', "%{$name}%")
                        ->orWhere('materno', 'like', "%{$name}%");
                });
            })
            ->when($municipalityId, fn ($query) => $query->whereHas(
                'community',
                fn ($communityQuery) => $communityQuery->where('municipality_id', $municipalityId)
            ))
            ->when($communityId, fn ($query) => $query->where('community_id', $communityId))
            ->when($levelName !== '', fn ($query) => $query->whereHas(
                'activeLevelAssignments.level',
                fn ($levelQuery) => $levelQuery->where('name', $levelName)
            ))
            ->orderBy('paterno')
            ->orderBy('materno')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'church_id', 'community_id', 'name', 'paterno', 'materno', 'code'])
            ->map(fn (Child $child): array => [
                'id' => $child->id,
                'church_id' => $child->church_id,
                'community_id' => $child->community_id,
                'code' => $child->code,
                'full_name' => trim(collect([$child->name, $child->paterno, $child->materno])->filter()->implode(' ')),
                'label' => trim(collect([$child->code, $child->name, $child->paterno, $child->materno])->filter()->implode(' · ')),
                'church' => $child->church?->name,
                'community' => $child->community?->name,
                'levels' => $child->activeLevelAssignments
                    ->map(fn ($assignment): array => [
                        'id' => $assignment->level?->id,
                        'name' => $assignment->level?->name,
                    ])
                    ->filter(fn (array $level): bool => $level['id'] !== null)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function getWeekends(User $user, ?int $limit = null): array
    {
        $scope = new UserScopeService($user);

        $query = $scope->applyWeekendScope(
            Weekend::query()->with('church:id,name')->orderByDesc('starts_at')
        )->get(['id', 'church_id', 'name', 'starts_at', 'ends_at', 'status']);

        if ($limit) {
            $query = $query->take($limit);
        }

        return $query->map(fn (Weekend $weekend): array => [
            'id' => $weekend->id,
            'church_id' => $weekend->church_id,
            'label' => collect([
                $weekend->name ?: $weekend->starts_at?->format('Y-m-d'),
                $weekend->starts_at?->format('Y-m-d h:i A'),
                $weekend->ends_at?->format('Y-m-d h:i A'),
                $weekend->church?->name,
            ])->filter()->implode(' · '),
            'church' => $weekend->church?->name,
            'starts_at' => $weekend->starts_at?->format('Y-m-d h:i A'),
            'ends_at' => $weekend->ends_at?->format('Y-m-d h:i A'),
            'status' => $weekend->status,
        ])
            ->values()
            ->all();
    }

    public function getMasses(User $user, int $weekendId): array
    {
        $scope = new UserScopeService($user);

        return $scope->applyMassScope(
            Mass::query()
                ->with(['church:id,name', 'chapel:id,name'])
                ->where('weekend_id', $weekendId)
                ->orderBy('starts_at')
        )
            ->get(['id', 'weekend_id', 'church_id', 'chapel_id', 'name', 'starts_at', 'ends_at', 'attendance_status'])
            ->map(fn (Mass $mass): array => [
                'id' => $mass->id,
                'weekend_id' => $mass->weekend_id,
                'label' => collect([
                    $mass->starts_at?->format('Y-m-d h:i A'),
                    $mass->ends_at?->format('Y-m-d h:i A'),
                    $mass->chapel?->name ?: $mass->church?->name,
                ])->filter()->implode(' · '),
                'starts_at' => $mass->starts_at?->format('Y-m-d h:i A'),
                'ends_at' => $mass->ends_at?->format('Y-m-d h:i A'),
                'church' => $mass->church?->name,
                'chapel' => $mass->chapel?->name,
                'attendance_status' => $mass->attendance_status,
            ])
            ->values()
            ->all();
    }

    public function getFilterOptions(User $user): array
    {
        $scope = new UserScopeService($user);

        return [
            'levels' => Level::query()
                ->when(! $scope->isGlobal(), fn ($query) => $query->whereIn('diocese_id', $scope->dioceseIds()))
                ->where('status', Status::ACTIVE)
                ->select('name')
                ->distinct()
                ->orderBy('name')
                ->get()
                ->map(fn (Level $level): array => [
                    'id' => $level->name,
                    'name' => $level->name,
                ])
                ->values()
                ->all(),
            'municipalities' => Municipality::query()
                ->when(! $scope->isGlobal(), fn ($query) => $query->whereIn('id', $scope->municipalityIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'state_id', 'name'])
                ->map(fn (Municipality $municipality): array => [
                    'id' => $municipality->id,
                    'name' => $municipality->name,
                ])
                ->values()
                ->all(),
            'communities' => Community::query()
                ->when(! $scope->isGlobal(), fn ($query) => $query->whereIn('id', $scope->communityIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'municipality_id', 'name'])
                ->map(fn (Community $community): array => [
                    'id' => $community->id,
                    'municipality_id' => $community->municipality_id,
                    'name' => $community->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Check if there's an active manual attendance movement available.
     * Used to validate if manual attendance registration is allowed.
     */
    public function isManualAttendanceCaptureActive(User $user): bool
    {
        // Get a period to check for active movements
        // We'll check if ANY active manual attendance movement exists within the user's scope
        $scope = new UserScopeService($user);

        $now = now()->toDateString();

        // Count active manual attendance movements
        return \App\Models\Operation\PeriodMovement::query()
            ->whereHas('periodMovementType', fn ($q) => $q->where('name', 'ASISTENCIA MANUAL'))
            ->where('status', \App\Globals\Status::IN_PROGRESS)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->whereHas('period', fn ($q) => $scope->isGlobal()
                ? $q
                : $q->whereIn('diocese_id', $scope->dioceseIds())
            )
            ->exists();
    }

    /**
     * Get the currently active manual attendance movement details.
     * Returns movement info or null if no active movement exists.
     */
    public function getActiveManualAttendanceMovementInfo(User $user): ?array
    {
        $scope = new UserScopeService($user);

        $now = now()->toDateString();

        $movement = \App\Models\Operation\PeriodMovement::query()
            ->with(['periodMovementType:id,name', 'period:id,diocese_id,name,years'])
            ->whereHas('periodMovementType', fn ($q) => $q->where('name', 'ASISTENCIA MANUAL'))
            ->where('status', \App\Globals\Status::IN_PROGRESS)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->whereHas('period', fn ($q) => $scope->isGlobal()
                ? $q
                : $q->whereIn('diocese_id', $scope->dioceseIds())
            )
            ->first(['id', 'period_id', 'period_movement_type_id', 'status', 'start_date', 'end_date']);

        if (! $movement) {
            return null;
        }

        return [
            'id' => $movement->id,
            'period_id' => $movement->period_id,
            'period_name' => $movement->period?->name,
            'type_name' => $movement->periodMovementType?->name,
            'status' => $movement->status,
            'start_date' => $movement->start_date->format('Y-m-d'),
            'end_date' => $movement->end_date->format('Y-m-d'),
        ];
    }
}
