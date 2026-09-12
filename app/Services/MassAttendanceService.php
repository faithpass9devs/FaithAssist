<?php

namespace App\Services;

use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MassAttendanceService
{
    public function register(Mass $mass, string $childCode, string $action, User $user): MassAttendance
    {
        if ($mass->attendance_status !== Status::IN_PROGRESS) {
            throw ValidationException::withMessages([
                'mass' => 'La captura de asistencias de esta misa no está en curso.',
            ]);
        }

        $child = Child::query()
            ->where('code', trim($childCode))
            ->where('status', Status::ACTIVE)
            ->first();

        if (! $child) {
            throw ValidationException::withMessages([
                'child_code' => 'No se encontró un niño activo con ese código.',
            ]);
        }

        if ((int) $child->church_id !== (int) $mass->church_id) {
            throw ValidationException::withMessages([
                'child_code' => 'El niño no pertenece a la parroquia de esta misa.',
            ]);
        }

        return DB::transaction(function () use ($mass, $child, $action, $user): MassAttendance {
            $attendance = MassAttendance::query()
                ->where('mass_id', $mass->id)
                ->where('child_id', $child->id)
                ->lockForUpdate()
                ->first();

            if ($action === Status::CHECK_IN) {
                return $this->checkIn($mass, $child, $user, $attendance);
            }

            return $this->checkOut($user, $attendance);
        });
    }

private function checkIn(Mass $mass, Child $child, User $user, ?MassAttendance $attendance): MassAttendance
{
    if ($attendance?->check_in_at) {
        throw ValidationException::withMessages([
            'child_code' => 'Este niño ya tiene entrada registrada en esta misa.',
        ]);
    }

    if ($attendance) {
        $attendance->update([
            'child_code' => $child->code,
            'church_id' => $mass->church_id,
            'chapel_id' => $mass->chapel_id,
            'check_in_at' => now(),
            'check_in_by' => $user->id,
            'check_out_at' => null,
            'check_out_by' => null,
            'status' => Status::CHECK_IN,
        ]);

        return $attendance->fresh(['child:id,name,paterno,materno,code', 'church:id,name', 'chapel:id,name']);
    }

    return MassAttendance::query()->create([
        'mass_id' => $mass->id,
        'child_id' => $child->id,
        'child_code' => $child->code,
        'church_id' => $mass->church_id,
        'chapel_id' => $mass->chapel_id,
        'check_in_at' => now(),
        'check_in_by' => $user->id,
        'status' => Status::CHECK_IN,
    ])->load(['child:id,name,paterno,materno,code', 'church:id,name', 'chapel:id,name']);
}

    private function checkOut(User $user, ?MassAttendance $attendance): MassAttendance
    {
        if (! $attendance?->check_in_at) {
            throw ValidationException::withMessages([
                'child_code' => 'No existe una entrada previa para registrar salida.',
            ]);
        }

        if ($attendance->check_out_at) {
            throw ValidationException::withMessages([
                'child_code' => 'Este niño ya tiene salida registrada en esta misa.',
            ]);
        }

        $attendance->update([
            'check_out_at' => now(),
            'check_out_by' => $user->id,
            'status' => Status::CHECK_OUT,
        ]);

        return $attendance->fresh(['child:id,name,paterno,materno,code', 'church:id,name', 'chapel:id,name']);
    }
}
