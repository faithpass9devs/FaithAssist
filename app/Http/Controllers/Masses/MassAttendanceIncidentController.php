<?php

namespace App\Http\Controllers\Masses;

use App\Globals\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\MassAttendanceIncidentRequest;
use App\Models\Catechism\Child;
use App\Models\Masses\IncidenceType;
use App\Models\Masses\MassAttendanceIncident;
use App\Models\Masses\Weekend;
use App\Services\MassAttendanceIncidentService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MassAttendanceIncidentController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(MassAttendanceIncident::class, 'attendanceIncident');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $weekendId = $request->integer('weekend_id') ?: null;
        $childId = $request->integer('child_id') ?: null;
        $status = $request->input('status');
        $scope = new UserScopeService($request->user());

        $incidents = $scope->applyMassAttendanceIncidentScope(
            MassAttendanceIncident::query()
                ->with([
                    'weekend.church:id,name',
                    'child:id,church_id,community_id,name,paterno,materno,code',
                    'incidenceType:id,name,description',
                ])
                ->when($weekendId, fn ($query) => $query->where('weekend_id', $weekendId))
                ->when($childId, fn ($query) => $query->where('child_id', $childId))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($search, function ($query) use ($search): void {
                    $query->where(function ($builder) use ($search): void {
                        $builder
                            ->where('description', 'like', "%{$search}%")
                            ->orWhereHas('child', function ($child) use ($search): void {
                                $child->where('code', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%")
                                    ->orWhere('paterno', 'like', "%{$search}%")
                                    ->orWhere('materno', 'like', "%{$search}%");
                            })
                            ->orWhereHas('incidenceType', fn ($type) => $type->where('name', 'like', "%{$search}%"));
                    });
                })
                ->latest()
        )
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MassAttendanceIncident $incident): array => $this->serializeIncident($incident));

        return Inertia::render('Masses/AttendanceIncidents/Index', [
            'incidents' => $incidents,
            'weekends' => $this->weekendOptions($request),
            'children' => $this->childOptions($request),
            'incidenceTypes' => $this->incidenceTypeOptions(),
            'statusOptions' => $this->statusOptions(),
            'search' => $search,
            'filters' => [
                'weekend_id' => $weekendId,
                'child_id' => $childId,
                'status' => $status,
            ],
        ]);
    }

    public function store(
        MassAttendanceIncidentRequest $request,
        MassAttendanceIncidentService $incidentService
    ): JsonResponse {
        $incident = $incidentService->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => $this->serializeIncident($incident),
            'message' => 'Incidencia de asistencia creada correctamente.',
        ], 201);
    }

    public function update(
        MassAttendanceIncidentRequest $request,
        MassAttendanceIncident $attendanceIncident,
        MassAttendanceIncidentService $incidentService
    ): JsonResponse {
        $incident = $incidentService->update($attendanceIncident, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => $this->serializeIncident($incident),
            'message' => 'Incidencia de asistencia actualizada correctamente.',
        ]);
    }

    public function destroy(MassAttendanceIncident $attendanceIncident): JsonResponse
    {
        $attendanceIncident->delete();

        return response()->json([
            'success' => true,
            'message' => 'Incidencia de asistencia eliminada correctamente.',
        ]);
    }

    private function weekendOptions(Request $request): array
    {
        $scope = new UserScopeService($request->user());

        return $scope->applyWeekendScope(
            Weekend::query()
                ->with('church:id,name')
                ->orderByDesc('starts_at')
        )
            ->get(['id', 'church_id', 'name', 'starts_at', 'ends_at', 'status'])
            ->map(fn (Weekend $weekend): array => [
                'id' => $weekend->id,
                'church_id' => $weekend->church_id,
                'name' => $weekend->name ?: $weekend->starts_at?->format('Y-m-d'),
                'label' => ($weekend->name ?: $weekend->starts_at?->format('Y-m-d')).' · '.$weekend->church?->name,
                'starts_at' => $weekend->starts_at?->format('Y-m-d H:i'),
                'ends_at' => $weekend->ends_at?->format('Y-m-d H:i'),
                'status' => $weekend->status,
                'church' => $weekend->church?->name,
            ])
            ->all();
    }

    private function childOptions(Request $request): array
    {
        $scope = new UserScopeService($request->user());

        return ($scope->isGlobal()
            ? Child::query()
            : $scope->applyChildScope(Child::query()))
            ->where('status', Status::ACTIVE)
            ->orderBy('paterno')
            ->orderBy('materno')
            ->orderBy('name')
            ->get(['id', 'church_id', 'community_id', 'name', 'paterno', 'materno', 'code'])
            ->map(fn (Child $child): array => [
                'id' => $child->id,
                'church_id' => $child->church_id,
                'community_id' => $child->community_id,
                'name' => trim(collect([$child->name, $child->paterno, $child->materno])->filter()->implode(' ')),
                'code' => $child->code,
                'label' => trim(collect([$child->code, $child->name, $child->paterno, $child->materno])->filter()->implode(' · ')),
            ])
            ->values()
            ->all();
    }

    private function incidenceTypeOptions(): array
    {
        return IncidenceType::query()
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->all();
    }

    private function serializeIncident(MassAttendanceIncident $incident): array
    {
        $childName = trim(collect([
            $incident->child?->name,
            $incident->child?->paterno,
            $incident->child?->materno,
        ])->filter()->implode(' '));

        return [
            'id' => $incident->id,
            'weekend_id' => $incident->weekend_id,
            'child_id' => $incident->child_id,
            'incidence_type_id' => $incident->incidence_type_id,
            'weekend' => $incident->weekend?->name ?: $incident->weekend?->starts_at?->format('Y-m-d'),
            'church' => $incident->weekend?->church?->name,
            'child_name' => $childName,
            'child_code' => $incident->child?->code,
            'incidence_type' => $incident->incidenceType?->name,
            'incidence_type_description' => $incident->incidenceType?->description,
            'description' => $incident->description,
            'status' => $incident->status,
            'created_at' => $incident->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function statusOptions(): array
    {
        return [
            ['value' => Status::ACTIVE, 'label' => 'Activo'],
            ['value' => Status::INACTIVE, 'label' => 'Inactivo'],
        ];
    }
}
