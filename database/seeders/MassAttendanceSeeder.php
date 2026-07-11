<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Ecclesiastes\Chapel;
use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Models\Masses\Weekend;
use App\Models\User;
use Illuminate\Database\Seeder;

class MassAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontro el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $weekends = Weekend::query()
            ->with(['masses' => fn ($query) => $query->orderBy('starts_at')])
            ->orderBy('starts_at')
            ->get();

        if ($weekends->isEmpty()) {
            $this->command?->warn('No hay fines de semana para generar asistencias.');

            return;
        }

        foreach ($weekends as $weekend) {
            $children = Child::query()
                ->where('church_id', $weekend->church_id)
                ->where('status', Status::ACTIVE)
                ->orderBy('id')
                ->get();

            if ($children->isEmpty()) {
                continue;
            }

            foreach ($weekend->masses as $massIndex => $mass) {
                $chapelCommunityId = null;

                if ($mass->chapel_id !== null) {
                    $chapelCommunityId = Chapel::query()
                        ->where('id', $mass->chapel_id)
                        ->value('community_id');
                }

                $massChildren = $chapelCommunityId
                    ? $children->where('community_id', $chapelCommunityId)->values()
                    : $children->values();

                if ($massChildren->isEmpty()) {
                    continue;
                }

                $selectedChildren = $massChildren->take(6);

                foreach ($selectedChildren as $childIndex => $child) {
                    $status = Status::PENDING;
                    $checkInAt = null;
                    $checkOutAt = null;
                    $notes = null;

                    if ($weekend->status === Status::UPCOMING) {
                        $notes = 'Asistencia pendiente para misa proxima.';
                    } elseif ($weekend->status === Status::COMPLETED || $weekend->status === Status::IN_PROGRESS) {
                        $pattern = ($childIndex + $massIndex) % 3;

                        if ($pattern === 0) {
                            $status = Status::CHECK_OUT;
                            $checkInAt = $mass->starts_at?->copy()->subMinutes(15);
                            $checkOutAt = $mass->starts_at?->copy()->addMinutes(50);
                        } elseif ($pattern === 1) {
                            $status = Status::CHECK_IN;
                            $checkInAt = $mass->starts_at?->copy()->subMinutes(10);
                            $notes = 'Entrada registrada sin salida en prueba.';
                        } else {
                            $status = Status::FAILED;
                            $notes = 'Falta de prueba para justificar por incidencia.';
                        }
                    }

                    MassAttendance::query()->updateOrCreate(
                        [
                            'mass_id' => $mass->id,
                            'child_id' => $child->id,
                        ],
                        [
                            'child_code' => $child->code,
                            'church_id' => $mass->church_id,
                            'chapel_id' => $mass->chapel_id,
                            'check_in_at' => $checkInAt?->format('Y-m-d H:i:s'),
                            'check_in_by' => $checkInAt ? $superadmin->id : null,
                            'check_out_at' => $checkOutAt?->format('Y-m-d H:i:s'),
                            'check_out_by' => $checkOutAt ? $superadmin->id : null,
                            'status' => $status,
                            'notes' => $notes,
                        ]
                    );
                }
            }
        }

        $this->command?->info('Asistencias de prueba creadas exitosamente.');
    }
}
