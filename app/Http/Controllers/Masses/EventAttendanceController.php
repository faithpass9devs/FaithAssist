<?php

namespace App\Http\Controllers\Masses;

use App\Globals\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\EventAttendanceCaptureStatusRequest;
use App\Http\Requests\Masses\EventAttendanceScanRequest;
use App\Models\Masses\Event;
use App\Models\Masses\EventAttendance;
use App\Services\EventAttendanceService;
use App\Services\Masses\EventAttendanceDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventAttendanceController extends Controller
{
    public function __construct(
        private readonly EventAttendanceDataService $dataService,
        private readonly EventAttendanceService $attendanceService,
    ) {}

    public function index(Request $request, Event $evento): Response
    {
        $user = $request->user();
        $canRead = $user->can('event_attendance.read');
        $canScan = $user->can('event_attendance.scan');

        abort_unless($canRead || $canScan, 403);

        if ($canScan) {
            $this->authorize('scan', [EventAttendance::class, $evento]);
        } else {
            $this->authorize('read', [EventAttendance::class, $evento]);
        }

        return Inertia::render('Masses/EventAttendance/Scan', $this->dataService->getIndexData($user, $evento, $canRead, $canScan));
    }

    public function scan(EventAttendanceScanRequest $request, Event $evento): JsonResponse
    {
        $this->authorize('scan', [EventAttendance::class, $evento]);

        $attendance = $this->attendanceService->register(
            $evento,
            $request->string('attendee_code')->toString(),
            $request->string('action')->toString(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $attendance->id,
                'attendee_id' => $attendance->attendee_id,
                'attendee_type' => $attendance->attendee_type,
                'attendee_kind' => $attendance->isChildAttendee() ? 'niño' : 'personal',
                'attendee_code' => $attendance->attendee_code,
                'attendee_name' => trim(collect([
                    $attendance->attendee?->name,
                    $attendance->attendee?->paterno,
                    $attendance->attendee?->materno,
                ])->filter()->implode(' ')),
                'church' => $attendance->church?->name,
                'location' => $attendance->church?->name,
                'check_in_at' => $attendance->check_in_at?->format('Y-m-d H:i:s'),
                'check_out_at' => $attendance->check_out_at?->format('Y-m-d H:i:s'),
                'status' => $attendance->status,
                'valid' => $attendance->isValidAttendance(),
            ],
            'message' => $attendance->isValidAttendance()
                ? 'Salida registrada. La asistencia ya es válida.'
                : 'Entrada registrada correctamente.',
        ]);
    }

    public function updateCaptureStatus(EventAttendanceCaptureStatusRequest $request, Event $evento): JsonResponse
    {
        $this->authorize('manage', [EventAttendance::class, $evento]);

        $event = $this->attendanceService->setCaptureStatus(
            $evento,
            $request->string('capture')->toString(),
            $request->string('status')->toString()
        );

        return response()->json([
            'success' => true,
            'message' => $request->string('status')->toString() === Status::COMPLETED
                ? 'Captura terminada correctamente.'
                : 'Captura reabierta correctamente.',
            'data' => $this->dataService->serializeEvent($event),
        ]);
    }
}
