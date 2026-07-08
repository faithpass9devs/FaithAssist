<?php

namespace App\Services;

use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Models\Masses\MassAttendanceIncident;
use App\Models\Masses\Weekend;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MassAttendanceIncidentService
{
    public function create(array $data, User $user): MassAttendanceIncident
    {
        return DB::transaction(function () use ($data, $user): MassAttendanceIncident {
            $this->ensureNoActiveDuplicate((int) $data['weekend_id'], (int) $data['child_id']);

            $this->ensureJustifiableAbsence($data);
            $this->syncMissingAbsences($data, $user);

            return MassAttendanceIncident::query()->create([
                ...$data,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->load(['weekend.church:id,name', 'child:id,name,paterno,materno,code', 'incidenceType:id,name,description']);
        });
    }

    public function update(MassAttendanceIncident $incident, array $data, User $user): MassAttendanceIncident
    {
        return DB::transaction(function () use ($incident, $data, $user): MassAttendanceIncident {
            if ($data['status'] === Status::ACTIVE) {
                $this->ensureNoActiveDuplicate((int) $data['weekend_id'], (int) $data['child_id'], $incident->id);
                $this->ensureJustifiableAbsence($data);
                $this->syncMissingAbsences($data, $user);
            }

            $incident->update([
                ...$data,
                'updated_by' => $user->id,
            ]);

            return $incident->fresh(['weekend.church:id,name', 'child:id,name,paterno,materno,code', 'incidenceType:id,name,description']);
        });
    }

    private function ensureNoActiveDuplicate(int $weekendId, int $childId, ?int $exceptId = null): void
    {
        $exists = MassAttendanceIncident::query()
            ->where('weekend_id', $weekendId)
            ->where('child_id', $childId)
            ->where('status', Status::ACTIVE)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'child_id' => 'Este niño ya tiene una incidencia activa para el fin de semana seleccionado.',
            ]);
        }
    }

    private function ensureJustifiableAbsence(array $data): void
    {
        $weekend = Weekend::query()->findOrFail($data['weekend_id']);
        $child = Child::query()->findOrFail($data['child_id']);

        $hasJustifiableMass = $this->applicableMasses($weekend, $child)
            ->contains(function (Mass $mass) use ($child): bool {
                $attendance = $mass->attendances->firstWhere('child_id', $child->id);

                return ! $attendance?->isValidAttendance();
            });

        if (! $hasJustifiableMass) {
            throw ValidationException::withMessages([
                'weekend_id' => 'El niño no tiene faltas pendientes por justificar en este fin de semana.',
            ]);
        }
    }

    private function syncMissingAbsences(array $data, User $user): void
    {
        $weekend = Weekend::query()->findOrFail($data['weekend_id']);
        $child = Child::query()->findOrFail($data['child_id']);

        foreach ($this->applicableMasses($weekend, $child) as $mass) {
            $attendance = MassAttendance::query()
                ->where('mass_id', $mass->id)
                ->where('child_id', $child->id)
                ->lockForUpdate()
                ->first();

            if ($attendance !== null) {
                continue;
            }

            MassAttendance::query()->create([
                'mass_id' => $mass->id,
                'child_id' => $child->id,
                'child_code' => $child->code,
                'church_id' => $mass->church_id,
                'chapel_id' => $mass->chapel_id,
                'status' => Status::FAILED,
                'notes' => 'FALTA GENERADA POR INCIDENCIA DE ASISTENCIA.',
            ]);
        }
    }

    private function applicableMasses(Weekend $weekend, Child $child)
    {
        return Mass::query()
            ->with(['chapel:id,community_id', 'attendances' => fn ($query) => $query->where('child_id', $child->id)])
            ->where('weekend_id', $weekend->id)
            ->where('church_id', $child->church_id)
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Mass $mass): bool => $mass->chapel_id === null
                || $mass->chapel?->community_id === null
                || (int) $mass->chapel->community_id === (int) $child->community_id)
            ->values();
    }
}
