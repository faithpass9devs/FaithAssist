<?php

namespace App\Http\Controllers\Masses;

use App\Globals\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\MassAttendanceScanRequest;
use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Models\Masses\MassAttendanceIncident;
use App\Services\MassAttendanceService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MassAttendanceController extends Controller
{
    public function landing(Request $request): RedirectResponse
    {
        $user = $request->user();
        $canRead = $user->can('mass_attendance.read');
        $canScan = $user->can('mass_attendance.scan');

        abort_unless($canRead || $canScan, 403);

        $mass = $this->availableAttendanceMasses($request)->first();

        if (! $mass) {
            return redirect()->route('misas.index')
                ->with('warning', 'No hay misas disponibles para registrar asistencias.');
        }

        return redirect()->route('misas.asistencias.index', $mass);
    }

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

        $misa->loadMissing(['weekend:id,name,starts_at,ends_at', 'church:id,name', 'chapel:id,name']);

        $attendances = MassAttendance::query()
            ->with(['child:id,name,paterno,materno,code', 'church:id,name', 'chapel:id,name', 'mass:id,weekend_id'])
            ->where('mass_id', $misa->id)
            ->when(! $canRead, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MassAttendance $attendance): array => $this->serializeAttendance($attendance));

        return Inertia::render('Masses/Attendance/Scan', [
            'mass' => $this->serializeMass($misa),
            'attendances' => $attendances,
            'weekendOptions' => $this->attendanceWeekendOptions($request),
            'canScan' => $canScan,
        ]);
    }

    public function scan(
        MassAttendanceScanRequest $request,
        Mass $misa,
        MassAttendanceService $attendanceService
    ): JsonResponse {
        $this->authorize('scan', [MassAttendance::class, $misa]);

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
            'weekend_id' => $mass->weekend_id,
            'name' => $mass->name,
            'starts_at' => $mass->starts_at?->format('Y-m-d h:i A'),
            'ends_at' => $mass->ends_at?->format('Y-m-d h:i A'),
            'attendance_status' => $mass->attendance_status,
            'church' => $mass->church?->name,
            'chapel' => $mass->chapel?->name,
            'location' => $mass->chapel?->name ?: $mass->church?->name,
            'weekend' => $mass->weekend?->name ?: $mass->weekend?->starts_at?->format('Y-m-d'),
        ];
    }

    private function availableAttendanceMasses(Request $request)
    {
        $scope = new UserScopeService($request->user());
        $now = now();
        $nowTimestamp = $now->getTimestamp();

        return $scope->applyMassScope(
            Mass::query()->with(['weekend:id,name,starts_at,ends_at', 'church:id,name', 'chapel:id,name'])
        )
            ->get()
            ->sortBy(function (Mass $mass) use ($nowTimestamp): string {
                $startTimestamp = $mass->starts_at?->getTimestamp()
                    ?? $mass->weekend?->starts_at?->getTimestamp()
                    ?? PHP_INT_MAX;
                $endTimestamp = $mass->ends_at?->getTimestamp()
                    ?? $mass->weekend?->ends_at?->getTimestamp()
                    ?? $startTimestamp;

                if ($mass->attendance_status === 'in_progress' || ($startTimestamp <= $nowTimestamp && $endTimestamp >= $nowTimestamp)) {
                    $priority = 0;
                    $distance = 0;
                } elseif ($startTimestamp >= $nowTimestamp) {
                    $priority = 1;
                    $distance = $startTimestamp - $nowTimestamp;
                } else {
                    $priority = 2;
                    $distance = $nowTimestamp - $endTimestamp;
                }

                return sprintf('%d-%020d-%020d', $priority, $distance, $startTimestamp);
            })
            ->values();
    }

    private function attendanceWeekendOptions(Request $request): array
    {
        return $this->availableAttendanceMasses($request)
            ->groupBy('weekend_id')
            ->map(function ($masses) {
                $firstMass = $masses->first();
                $weekend = $firstMass?->weekend;

                return [
                    'id' => $firstMass?->weekend_id,
                    'label' => $weekend?->name ?: $weekend?->starts_at?->format('Y-m-d') ?: 'Sin fin de semana',
                    'starts_at' => $weekend?->starts_at?->format('Y-m-d h:i A'),
                    'masses' => $masses->map(function (Mass $mass): array {
                        $location = $mass->chapel?->name ?: $mass->church?->name;
                        $schedule = collect([
                            $mass->starts_at?->format('Y-m-d h:i A'),
                            $mass->ends_at?->format('Y-m-d h:i A'),
                        ])->filter()->implode(' - ');

                        return [
                            'id' => $mass->id,
                            'short_label' => $schedule,
                            'label' => collect([
                                $schedule,
                                $location,
                            ])->filter()->implode(' · '),
                            'location' => $location,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function serializeAttendance(MassAttendance $attendance): array
    {
        $childName = trim(collect([
            $attendance->child?->name,
            $attendance->child?->paterno,
            $attendance->child?->materno,
        ])->filter()->implode(' '));
        $incident = $this->activeIncident($attendance);

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
            'justified' => $incident !== null,
            'incidence_type' => $incident?->incidenceType?->name,
            'incidence_description' => $incident?->description,
        ];
    }

    private function activeIncident(MassAttendance $attendance): ?MassAttendanceIncident
    {
        if (! $attendance->mass?->weekend_id || ! $attendance->child_id) {
            return null;
        }

        return MassAttendanceIncident::query()
            ->with('incidenceType:id,name')
            ->where('weekend_id', $attendance->mass->weekend_id)
            ->where('child_id', $attendance->child_id)
            ->where('status', Status::ACTIVE)
            ->first();
    }
}
