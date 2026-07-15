<?php

namespace App\Http\Controllers\Masses;

use App\Globals\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\ManualAttendanceRequest;
use App\Models\Catechism\Child;
use App\Models\Operation\Level;
use App\Models\Masses\ManualAttendance;
use App\Models\Masses\Mass;
use App\Models\Masses\Weekend;
use App\Models\Regions\Community;
use App\Models\Regions\Municipality;
use App\Services\ManualAttendanceService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualAttendanceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ManualAttendance::class, 'manualAttendance');
    }

    public function index(Request $request): Response
    {
        $code = $request->input('code', '');
        $name = $request->input('name', '');
        $levelName = trim((string) $request->input('level_id', ''));
        $municipalityId = $request->integer('municipality_id') ?: null;
        $communityId = $request->integer('community_id') ?: null;
        $childId = $request->integer('child_id') ?: null;
        $weekendId = $request->integer('weekend_id') ?: null;
        $scope = new UserScopeService($request->user());

        $children = ($scope->isGlobal() ? Child::query() : $scope->applyChildScope(Child::query()))
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
            ->limit(20)
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

        $filterOptions = $this->filterOptions($request);

        $weekends = $scope->applyWeekendScope(
            Weekend::query()->with('church:id,name')->orderByDesc('starts_at')
        )
            ->get(['id', 'church_id', 'name', 'starts_at', 'ends_at', 'status'])
            ->map(fn (Weekend $weekend): array => [
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

        $selectedWeekendId = $weekendId;

        $masses = $selectedWeekendId
            ? $scope->applyMassScope(
                Mass::query()
                    ->with(['church:id,name', 'chapel:id,name'])
                    ->where('weekend_id', $selectedWeekendId)
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
                ->all()
            : [];

        return Inertia::render('Masses/ManualAttendance/Index', [
            'children' => $children,
            'weekends' => $weekends,
            'masses' => $masses,
            'levels' => $filterOptions['levels'],
            'municipalities' => $filterOptions['municipalities'],
            'communities' => $filterOptions['communities'],
            'filters' => [
                'code' => $code,
                'name' => $name,
                'level_id' => $levelName !== '' ? $levelName : null,
                'municipality_id' => $municipalityId,
                'community_id' => $communityId,
                'child_id' => $childId,
                'weekend_id' => $selectedWeekendId,
            ],
        ]);
    }

    public function store(ManualAttendanceRequest $request, ManualAttendanceService $manualAttendanceService): JsonResponse
    {
        $attendances = $manualAttendanceService->register($request->validated(), $request->user());
        $count = $attendances->count();

        return response()->json([
            'success' => true,
            'count' => $count,
            'data' => $attendances->map(fn ($attendance) => [
                'id' => $attendance->id,
                'mass_id' => $attendance->mass_id,
                'child_id' => $attendance->child_id,
                'child_name' => trim(collect([
                    $attendance->child?->name,
                    $attendance->child?->paterno,
                    $attendance->child?->materno,
                ])->filter()->implode(' ')),
                'mass' => $attendance->mass?->id,
            ]),
            'message' => $count === 1
                ? 'Asistencia manual registrada correctamente.'
                : "{$count} asistencias manuales registradas correctamente.",
        ], 201);
    }

    private function filterOptions(Request $request): array
    {
        $scope = new UserScopeService($request->user());

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
}
