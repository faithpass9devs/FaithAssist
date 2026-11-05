<?php

namespace App\Services;

use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Masses\Event;
use App\Models\Masses\EventAttendance;
use App\Models\Operation\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventAttendanceService
{
    public function register(Event $event, string $attendeeCode, string $action, User $user): EventAttendance
    {
        $captureStatus = $action === Status::CHECK_IN
            ? $event->attendance_check_in_status
            : $event->attendance_check_out_status;

        if ($captureStatus !== Status::IN_PROGRESS) {
            throw ValidationException::withMessages([
                'event' => $action === Status::CHECK_IN
                    ? 'La captura de entradas de este evento no está en curso.'
                    : 'La captura de salidas de este evento no está en curso.',
            ]);
        }

        $attendee = $this->resolveAttendee(trim($attendeeCode), (int) $event->church_id);

        return DB::transaction(function () use ($event, $attendee, $action, $user): EventAttendance {
            /** @var EventAttendance|null $attendance */
            $attendance = EventAttendance::query()
                ->where('event_id', $event->id)
                ->where('attendee_type', $attendee::class)
                ->where('attendee_id', $attendee->getKey())
                ->lockForUpdate()
                ->first();

            if ($action === Status::CHECK_IN) {
                if ($attendance && $attendance->check_in_at) {
                    throw ValidationException::withMessages([
                        'attendee_code' => 'La entrada ya fue registrada para este código.',
                    ]);
                }

                if (! $attendance) {
                    $attendance = EventAttendance::query()->create([
                        'event_id' => $event->id,
                        'attendee_type' => $attendee::class,
                        'attendee_id' => $attendee->getKey(),
                        'attendee_code' => (string) $attendee->getAttribute('code'),
                        'church_id' => $event->church_id,
                        'check_in_at' => now(),
                        'check_in_by' => $user->id,
                        'status' => Status::CHECK_IN,
                    ]);
                } else {
                    $attendance->update([
                        'check_in_at' => now(),
                        'check_in_by' => $user->id,
                        'status' => Status::CHECK_IN,
                    ]);
                }

                return $attendance->fresh(['attendee', 'church']);
            }

            if (! $attendance || ! $attendance->check_in_at) {
                throw ValidationException::withMessages([
                    'attendee_code' => 'No existe entrada registrada para este código.',
                ]);
            }

            if ($attendance->check_out_at) {
                throw ValidationException::withMessages([
                    'attendee_code' => 'La salida ya fue registrada para este código.',
                ]);
            }

            $attendance->update([
                'check_out_at' => now(),
                'check_out_by' => $user->id,
                'status' => Status::CHECK_OUT,
            ]);

            return $attendance->fresh(['attendee', 'church']);
        });
    }

    public function setCaptureStatus(Event $event, string $capture, string $status): Event
    {
        $column = $capture === Status::CHECK_IN
            ? 'attendance_check_in_status'
            : 'attendance_check_out_status';

        $event->update([$column => $status]);

        return $event->fresh(['church']);
    }

    private function resolveAttendee(string $code, int $eventChurchId): Model
    {
        $child = Child::query()
            ->where('code', $code)
            ->where('status', Status::ACTIVE)
            ->first();

        $staff = Staff::query()
            ->where('code', $code)
            ->first();

        if ($child && $staff) {
            throw ValidationException::withMessages([
                'attendee_code' => 'El código existe en niño y personal. Corrige el código antes de continuar.',
            ]);
        }

        $attendee = $child ?: $staff;

        if (! $attendee) {
            throw ValidationException::withMessages([
                'attendee_code' => 'No se encontró un niño o personal activo con ese código.',
            ]);
        }

        if ((int) $attendee->getAttribute('church_id') !== $eventChurchId) {
            throw ValidationException::withMessages([
                'attendee_code' => 'El código no pertenece a la parroquia de este evento.',
            ]);
        }

        return $attendee;
    }
}
