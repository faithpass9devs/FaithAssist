<?php

namespace App\Http\Controllers\Catechism;

use App\Globals\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catechism\ReinscriptionRequest;
use App\Models\Catechism\Child;
use App\Models\Catechism\ChildLevelAssignment;
use App\Models\Catechism\ChildReinscription;
use App\Models\Operation\Level;
use App\Models\Regions\Community;
use App\Services\CatechismPeriodMovementService;
use App\Services\UserScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReinscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('reinscripciones.read'), 403);

        $search = $request->input('search', '');
        $communityId = $request->integer('community_id') ?: null;
        $levelId = $request->integer('level_id') ?: null;
        $scope = new UserScopeService($request->user());

        $query = $this->eligibleChildrenQuery($request)
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

        $children = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Child $child): array => $this->serializeChild($child));

        return Inertia::render('Catechism/Reinscriptions/Index', [
            'children' => $children,
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
        ]);
    }

    public function create(Request $request, Child $child): Response
    {
        abort_unless($request->user()->can('reinscripciones.create'), 403);

        $scope = new UserScopeService($request->user());
        $child = $this->eligibleChildrenQuery($request)
            ->whereKey($child->id)
            ->firstOrFail();

        return Inertia::render('Catechism/Reinscriptions/Form', [
            'child' => $this->serializeChild($child),
            'levels' => Level::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('diocese_id', $scope->dioceseIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'diocese_id', 'name']),
        ]);
    }

    public function store(ReinscriptionRequest $request, CatechismPeriodMovementService $movementService): RedirectResponse
    {
        $data = $request->validated();
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

        DB::transaction(function () use ($child, $movement, $toLevelIds, $data, $request): void {
            $fromLevelIds = $child->activeLevelAssignments->pluck('level_id')->unique()->values();

            ChildReinscription::create([
                'child_id' => $child->id,
                'period_id' => $movement->period_id,
                'period_movement_id' => $movement->id,
                'from_level_ids' => $fromLevelIds->all(),
                'to_level_ids' => $toLevelIds->all(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            ChildLevelAssignment::query()
                ->where('child_id', $child->id)
                ->where('status', Status::ACTIVE)
                ->update([
                    'status' => Status::COMPLETED,
                    'ended_at' => now()->toDateString(),
                    'updated_by' => $request->user()?->id,
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
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                ]);
            }
        });

        return redirect()->route('reinscripciones.index')
            ->with('success', 'Reinscripción registrada correctamente.');
    }

    private function eligibleChildrenQuery(Request $request): Builder
    {
        $scope = new UserScopeService($request->user());
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

    private function serializeChild(Child $child): array
    {
        return [
            'id' => $child->id,
            'code' => $child->code,
            'full_name' => trim(collect([$child->name, $child->paterno, $child->materno])->filter()->implode(' ')),
            'church' => $child->church?->name,
            'community' => $child->community?->name,
            'diocese_id' => $child->church?->deanery?->diocese_id,
            'birthdate' => $child->birthdate?->format('Y-m-d'),
            'sex' => $child->sex,
            'email' => $child->email,
            'phone' => $child->phone,
            'emergency_phone' => $child->emergency_phone,
            'blood_type' => $child->blood_type,
            'observations' => $child->observations,
            'levels' => $child->activeLevelAssignments
                ->map(fn (ChildLevelAssignment $assignment): array => [
                    'assignment_id' => $assignment->id,
                    'id' => $assignment->level?->id,
                    'name' => $assignment->level?->name,
                ])
                ->filter(fn (array $level): bool => $level['id'] !== null)
                ->values()
                ->all(),
        ];
    }
}
