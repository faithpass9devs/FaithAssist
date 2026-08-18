<?php

namespace App\Http\Controllers\Masses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\ManualAttendanceRequest;
use App\Models\Masses\ManualAttendance;
use App\Services\ManualAttendanceService;
use App\Services\Masses\ManualAttendanceDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualAttendanceController extends Controller
{
    public function __construct(
        private readonly ManualAttendanceDataService $dataService,
        private readonly ManualAttendanceService $manualAttendance,
    ) {
        $this->authorizeResource(ManualAttendance::class, 'manualAttendance');
    }

    public function index(Request $request): Response
    {
        $code = $request->input('code', '');
        $name = $request->input('name', '');
        $levelId = trim((string) $request->input('level_id', ''));
        $municipalityId = $request->integer('municipality_id') ?: null;
        $communityId = $request->integer('community_id') ?: null;
        $childId = $request->integer('child_id') ?: null;
        $weekendId = $request->integer('weekend_id') ?: null;

        return Inertia::render('Masses/ManualAttendance/Index', $this->dataService->indexData(
            $request->user(),
            $code,
            $name,
            $levelId,
            $municipalityId,
            $communityId,
            $childId,
            $weekendId,
        ));
    }

    public function store(ManualAttendanceRequest $request): JsonResponse
    {
        // Validate that manual attendance movement is currently active
        if (! $this->dataService->isManualAttendanceCaptureActive($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'No hay un movimiento activo de asistencia manual. No se pueden registrar asistencias en este momento.',
            ], 409);
        }

        $attendances = $this->manualAttendance->register($request->validated(), $request->user());
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
}
