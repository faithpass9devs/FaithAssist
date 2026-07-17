<?php

namespace App\Services\Catechism;

use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Catechism\ChildLevelAssignment;
use App\Models\Catechism\ChildReinscription;
use App\Models\Operation\Level;
use App\Models\Regions\Community;
use App\Models\User;
use App\Services\CatechismPeriodMovementService;
use App\Services\UserScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReinscriptionService
{
    public function indexData(User $user, string $search, ?int $communityId = null, ?int $levelId = null): array
    {
        $scope = new UserScopeService($user);

        $query = $this->eligibleChildrenQuery($user)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('paterno', 'like', "%{$search}%")
                        ->orWhere('materno', 'like', "%{$search}%")
                        ->orWhereHas('church', fn ($church) => $church->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($communityId, fn ($query) => $query->where('community_id', $communityId))
            ->when($levelId, fn ($query) => $query->whereHas(
                'activeLevelAssignments',
                fn ($assignment) => $assignment->where('level_id', $levelId)
            ))
            ->orderBy('paterno')
            ->orderBy('materno')
            ->orderBy('name');

        $paginator = $query->paginate(15)->withQueryString();

        // Transform children to expected shape for the Vue component
        $paginator->through(fn (Child $child): array => [
            'id' => $child->id,
            'code' => $child->code,
            'full_name' => trim(collect([$child->name, $child->paterno, $child->materno])->filter()->implode(' ')),
            'community' => $child->community?->name,
            'church' => $child->church?->name,
            'levels' => $child->activeLevelAssignments
                ->map(fn ($assignment): array => [
                    'id' => $assignment->level?->id,
                    'name' => $assignment->level?->name,
                ])
                ->filter(fn (array $level): bool => $level['id'] !== null)
                ->values()
                ->all(),
        ]);

        return [
            'children' => $paginator,
            'search' => $search,
            'filters' => [
                'community_id' => $communityId,
                'level_id' => $levelId,
            ],
            'communityOptions' => Community::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->communityIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values(),
            'levelOptions' => Level::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('diocese_id', $scope->dioceseIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values(),
        ];
    }

    public function getChildForReinscription(User $user, Child $child): array
    {
        $child = $this->eligibleChildrenQuery($user)
            ->whereKey($child->id)
            ->firstOrFail();

        return $this->serializeChildForForm($child);
    }

    private function serializeChildForForm(Child $child): array
    {
        return [
            'id' => $child->id,
            'code' => $child->code,
            'full_name' => trim(collect([$child->name, $child->paterno, $child->materno])->filter()->implode(' ')),
            'church' => $child->church?->name,
            'community' => $child->community?->name,
            'diocese_id' => $child->church?->deanery?->diocese_id,
            'birthdate' => $child->birthdate?->format('Y-m-d'),
            'email' => $child->email,
            'phone' => $child->phone,
            'emergency_phone' => $child->emergency_phone,
            'levels' => $child->activeLevelAssignments
                ->map(fn ($assignment): array => [
                    'assignment_id' => $assignment->id,
                    'id' => $assignment->level?->id,
                    'name' => $assignment->level?->name,
                ])
                ->filter(fn (array $level): bool => $level['id'] !== null)
                ->values()
                ->all(),
        ];
    }

    public function getFormData(User $user): array
    {
        $scope = new UserScopeService($user);

        return [
            'levels' => Level::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('diocese_id', $scope->dioceseIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'diocese_id', 'name']),
        ];
    }

    public function createReinscription(User $user, array $data, CatechismPeriodMovementService $movementService): void
    {
        $child = Child::query()
            ->with([
                'church:id,name,deanery_id',
                'church.deanery:id,diocese_id',
                'activeLevelAssignments',
            ])
            ->findOrFail($data['child_id']);

        $movement = $movementService->requireActiveMovementForChurch(
            $child->church,
            CatechismPeriodMovementService::REINSCRIPTIONS,
            'child_id'
        );

        $toLevelIds = collect($data['to_level_ids'])->unique()->values();

        DB::transaction(function () use ($child, $movement, $toLevelIds, $data, $user): void {
            $fromLevelIds = $child->activeLevelAssignments->pluck('level_id')->unique()->values();

            ChildReinscription::create([
                'child_id' => $child->id,
                'period_id' => $movement->period_id,
                'period_movement_id' => $movement->id,
                'from_level_ids' => $fromLevelIds->all(),
                'to_level_ids' => $toLevelIds->all(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            ChildLevelAssignment::query()
                ->where('child_id', $child->id)
                ->where('status', Status::ACTIVE)
                ->update([
                    'status' => Status::COMPLETED,
                    'ended_at' => now()->toDateString(),
                    'updated_by' => $user->id,
                ]);

            foreach ($toLevelIds as $levelId) {
                ChildLevelAssignment::create([
                    'child_id' => $child->id,
                    'level_id' => $levelId,
                    'period_id' => $movement->period_id,
                    'period_movement_id' => $movement->id,
                    'status' => Status::ACTIVE,
                    'assigned_at' => now()->toDateString(),
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }
        });
    }

    private function eligibleChildrenQuery(User $user): Builder
    {
        $scope = new UserScopeService($user);
        $query = Child::query()
            ->with([
                'church:id,name,deanery_id',
                'church.deanery:id,diocese_id',
                'community:id,name',
                'activeLevelAssignments.level:id,name,diocese_id',
            ])
            ->where('status', Status::ACTIVE)
            ->whereHas('activeLevelAssignments')
            ->whereDoesntHave('reinscriptions', fn ($q) => $q->whereHas('period', fn ($p) => $p->where('status', Status::IN_PROGRESS)));

        return $scope->isGlobal() ? $query : $scope->applyChildScope($query);
    }
}
