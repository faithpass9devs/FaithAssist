<?php

namespace App\Http\Controllers\Masses;

use App\Globals\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\MassAttendanceCaptureStatusRequest;
use App\Http\Requests\Masses\MassAttendanceScanRequest;
use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Services\MassAttendanceService;
use App\Services\Masses\MassAttendanceDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MassAttendanceController extends Controller
{
    public function __construct(
        private readonly MassAttendanceDataService $dataService,
        private readonly MassAttendanceService $attendanceService,
    ) {}

    public function index(Request $request, Mass $misa): Response
    {
        $user = $request->user();
        $canRead = $user->can('mass_attendance.read');
        $canScan = $user->can('mass_attendance.scan');

        abort_unless($canRead || $canScan, 403);

        if ($canScan) {
            $this->authorize('scan', [MassAttendance::class, $misa]);
        } else {
            $this->authorize('view', $misa);
        }

        return Inertia::render('Masses/Attendance/Scan', $this->dataService->getIndexData($user, $misa, $canRead, $canScan));
    }

    public function scan(
        MassAttendanceScanRequest $request,
        Mass $misa
    ): JsonResponse {
        $this->authorize('scan', [MassAttendance::class, $misa]);

        $attendance = $this->attendanceService->register(
            $misa,
            $request->string('child_code')->toString(),
            $request->string('action')->toString(),
            $request->user()
        );
        $incident = $attendance->activeIncident();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $attendance->id,
                'child_id' => $attendance->child_id,
                'child_code' => $attendance->child_code,
                'child_name' => trim(collect([
                    $attendance->child?->name,
                    $attendance->child?->paterno,
                    $attendance->child?->materno,
                ])->filter()->implode(' ')),
                'church' => $attendance->church?->name,
                'chapel' => $attendance->chapel?->name,
                'location' => $attendance->chapel?->name ?: $attendance->church?->name,
                'check_in_at' => $attendance->check_in_at?->format('Y-m-d H:i:s'),
                'check_out_at' => $attendance->check_out_at?->format('Y-m-d H:i:s'),
                'status' => $attendance->status,
                'valid' => $attendance->isValidAttendance(),
                'justified' => $incident !== null,
                'incidence_type' => $incident?->incidenceType?->name,
                'incidence_description' => $incident?->description,
            ],
            'message' => $attendance->isValidAttendance()
                ? 'Salida registrada. La asistencia ya es válida.'
                : 'Entrada registrada correctamente.',
        ]);
    }

    public function updateCaptureStatus(
        MassAttendanceCaptureStatusRequest $request,
        Mass $misa
    ): JsonResponse {
        $this->authorize('manage', [MassAttendance::class, $misa]);

        $mass = $this->attendanceService->setCaptureStatus(
            $misa,
            $request->string('capture')->toString(),
            $request->string('status')->toString()
        );

        return response()->json([
            'success' => true,
            'message' => $request->string('status')->toString() === Status::COMPLETED
                ? 'Captura terminada correctamente.'
                : 'Captura reabierta correctamente.',
            'data' => $this->dataService->serializeMass($mass),
        ]);
    }
}
