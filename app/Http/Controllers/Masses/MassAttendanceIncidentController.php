<?php

namespace App\Http\Controllers\Masses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\MassAttendanceIncidentRequest;
use App\Models\Masses\MassAttendanceIncident;
use App\Services\MassAttendanceIncidentService;
use App\Services\Masses\MassAttendanceIncidentDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MassAttendanceIncidentController extends Controller
{
    public function __construct(
        private readonly MassAttendanceIncidentDataService $dataService,
        private readonly MassAttendanceIncidentService $incidentService,
    ) {
        $this->authorizeResource(MassAttendanceIncident::class, 'attendanceIncident');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $weekendId = $request->integer('weekend_id') ?: null;
        $childId = $request->integer('child_id') ?: null;
        $status = $request->input('status');

        return Inertia::render('Masses/AttendanceIncidents/Index', $this->dataService->indexData(
            $request->user(),
            $search,
            $weekendId,
            $childId,
            $status,
        ));
    }

    public function store(MassAttendanceIncidentRequest $request): JsonResponse
    {
        $incident = $this->incidentService->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $incident->id,
                'weekend_id' => $incident->weekend_id,
                'child_id' => $incident->child_id,
                'incidence_type_id' => $incident->incidence_type_id,
                'weekend' => $incident->weekend?->name ?: $incident->weekend?->starts_at?->format('Y-m-d'),
                'church' => $incident->weekend?->church?->name,
                'child_name' => trim(collect([
                    $incident->child?->name,
                    $incident->child?->paterno,
                    $incident->child?->materno,
                ])->filter()->implode(' ')),
                'child_code' => $incident->child?->code,
                'incidence_type' => $incident->incidenceType?->name,
                'incidence_type_description' => $incident->incidenceType?->description,
                'description' => $incident->description,
                'status' => $incident->status,
                'created_at' => $incident->created_at?->format('Y-m-d H:i'),
            ],
            'message' => 'Incidencia de asistencia creada correctamente.',
        ], 201);
    }

    public function update(MassAttendanceIncidentRequest $request, MassAttendanceIncident $attendanceIncident): JsonResponse
    {
        $incident = $this->incidentService->update($attendanceIncident, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $incident->id,
                'weekend_id' => $incident->weekend_id,
                'child_id' => $incident->child_id,
                'incidence_type_id' => $incident->incidence_type_id,
                'weekend' => $incident->weekend?->name ?: $incident->weekend?->starts_at?->format('Y-m-d'),
                'church' => $incident->weekend?->church?->name,
                'child_name' => trim(collect([
                    $incident->child?->name,
                    $incident->child?->paterno,
                    $incident->child?->materno,
                ])->filter()->implode(' ')),
                'child_code' => $incident->child?->code,
                'incidence_type' => $incident->incidenceType?->name,
                'incidence_type_description' => $incident->incidenceType?->description,
                'description' => $incident->description,
                'status' => $incident->status,
                'created_at' => $incident->created_at?->format('Y-m-d H:i'),
            ],
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
}
