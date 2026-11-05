<?php

namespace App\Repositories\Masses;

use App\Models\Catechism\Child;
use App\Models\Masses\Event;
use App\Models\Masses\EventAttendance;
use App\Models\Operation\Staff;

class EventAttendanceRepository
{
    public function getAttendances(Event $event, bool $canRead)
    {
        return EventAttendance::query()
            ->with(['attendee', 'church:id,name', 'event:id,name,starts_at,ends_at'])
            ->where('event_id', $event->id)
            ->when(! $canRead, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function serializeEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'starts_at' => $event->starts_at?->format('Y-m-d h:i A'),
            'ends_at' => $event->ends_at?->format('Y-m-d h:i A'),
            'attendance_check_in_status' => $event->attendance_check_in_status,
            'attendance_check_out_status' => $event->attendance_check_out_status,
            'church' => $event->church?->name,
            'location' => $event->church?->name,
        ];
    }

    public function serializeAttendance(EventAttendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'attendee_id' => $attendance->attendee_id,
            'attendee_type' => $attendance->attendee_type,
            'attendee_kind' => $attendance->isChildAttendee() ? 'niño' : 'personal',
            'attendee_code' => $attendance->attendee_code,
            'attendee_name' => $this->attendeeName($attendance),
            'church' => $attendance->church?->name,
            'location' => $attendance->church?->name,
            'check_in_at' => $attendance->check_in_at?->format('Y-m-d H:i:s'),
            'check_out_at' => $attendance->check_out_at?->format('Y-m-d H:i:s'),
            'status' => $attendance->status,
            'valid' => $attendance->isValidAttendance(),
        ];
    }

    private function attendeeName(EventAttendance $attendance): string
    {
        if ($attendance->attendee_type === Child::class) {
            /** @var Child|null $attendee */
            $attendee = $attendance->attendee;

            return trim(collect([
                $attendee?->name,
                $attendee?->paterno,
                $attendee?->materno,
            ])->filter()->implode(' '));
        }

        if ($attendance->attendee_type === Staff::class) {
            /** @var Staff|null $attendee */
            $attendee = $attendance->attendee;

            return trim(collect([
                $attendee?->name,
                $attendee?->paterno,
                $attendee?->materno,
            ])->filter()->implode(' '));
        }

        return 'Asistente';
    }
}
