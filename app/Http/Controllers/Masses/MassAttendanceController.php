<?php

namespace App\Http\Controllers\Masses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\MassAttendanceScanRequest;
use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Services\MassAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MassAttendanceController extends Controller
{
    public function index(Request $request, Mass $misa): Response
    {
        $this->authorize('view', $misa);
        abort_unless($request->user()->can('mass_attendance.read'), 403);

        $misa->loadMissing(['weekend:id,name,starts_at,ends_at', 'church:id,name', 'chapel:id,name']);

        $attendances = MassAttendance::query()
            ->with(['child:id,name,paterno,materno,code', 'church:id,name', 'chapel:id,name'])
            ->where('mass_id', $misa->id)
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MassAttendance $attendance): array => $this->serializeAttendance($attendance));

        return Inertia::render('Masses/Attendance/Scan', [
            'mass' => $this->serializeMass($misa),
            'attendances' => $attendances,
        ]);
    }

    public function scan(
        MassAttendanceScanRequest $request,
        Mass $misa,
        MassAttendanceService $attendanceService
    ): JsonResponse {
        $this->authorize('view', $misa);
        abort_unless($request->user()->can('mass_attendance.create'), 403);

        $attendance = $attendanceService->register(
            $misa,
            $request->string('child_code')->toString(),
            $request->string('action')->toString(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => $this->serializeAttendance($attendance),
            'message' => $attendance->isValidAttendance()
                ? 'Salida registrada. La asistencia ya es válida.'
                : 'Entrada registrada correctamente.',
        ]);
    }

    private function serializeMass(Mass $mass): array
    {
        return [
            'id' => $mass->id,
            'name' => $mass->name,
            'starts_at' => $mass->starts_at?->format('Y-m-d H:i'),
            'ends_at' => $mass->ends_at?->format('Y-m-d H:i'),
            'attendance_status' => $mass->attendance_status,
            'church' => $mass->church?->name,
            'chapel' => $mass->chapel?->name,
            'location' => $mass->chapel?->name ?: $mass->church?->name,
            'weekend' => $mass->weekend?->name ?: $mass->weekend?->starts_at?->format('Y-m-d'),
        ];
    }

    private function serializeAttendance(MassAttendance $attendance): array
    {
        $childName = trim(collect([
            $attendance->child?->name,
            $attendance->child?->paterno,
            $attendance->child?->materno,
        ])->filter()->implode(' '));

        return [
            'id' => $attendance->id,
            'child_id' => $attendance->child_id,
            'child_code' => $attendance->child_code,
            'child_name' => $childName,
            'church' => $attendance->church?->name,
            'chapel' => $attendance->chapel?->name,
            'location' => $attendance->chapel?->name ?: $attendance->church?->name,
            'check_in_at' => $attendance->check_in_at?->format('Y-m-d H:i:s'),
            'check_out_at' => $attendance->check_out_at?->format('Y-m-d H:i:s'),
            'status' => $attendance->status,
            'valid' => $attendance->isValidAttendance(),
        ];
    }
}
