<?php

namespace Database\Seeders;

use App\Models\Ecclesiastes\Chapel;
use App\Models\Masses\Mass;
use App\Models\Masses\Weekend;
use App\Models\User;
use Illuminate\Database\Seeder;

class MassSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontro el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $weekends = Weekend::query()
            ->with('church:id')
            ->orderBy('starts_at')
            ->get();

        if ($weekends->isEmpty()) {
            $this->command?->warn('No hay fines de semana para crear misas. Ejecuta MassWeekendSeeder primero.');

            return;
        }

        foreach ($weekends as $weekend) {
            $chapel = Chapel::query()
                ->where('church_id', $weekend->church_id)
                ->where('status', 'active')
                ->orderBy('id')
                ->first();

            $attendanceStatus = $weekend->status;

            $parishMassStarts = $weekend->starts_at->copy()->addDay()->setTime(10, 0);
            Mass::query()->updateOrCreate(
                [
                    'weekend_id' => $weekend->id,
                    'name' => 'MISA DOMINICAL PARROQUIAL',
                    'starts_at' => $parishMassStarts->format('Y-m-d H:i:s'),
                ],
                [
                    'church_id' => $weekend->church_id,
                    'chapel_id' => null,
                    'ends_at' => $parishMassStarts->copy()->addHour()->format('Y-m-d H:i:s'),
                    'status' => $weekend->status,
                    'attendance_status' => $attendanceStatus,
                    'notes' => 'Misa general de referencia para pruebas.',
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ]
            );

            if (! $chapel) {
                continue;
            }

            $chapelMassStarts = $weekend->starts_at->copy()->setTime(18, 0);
            Mass::query()->updateOrCreate(
                [
                    'weekend_id' => $weekend->id,
                    'name' => 'MISA SABATINA CAPILLA',
                    'starts_at' => $chapelMassStarts->format('Y-m-d H:i:s'),
                ],
                [
                    'church_id' => $weekend->church_id,
                    'chapel_id' => $chapel->id,
                    'ends_at' => $chapelMassStarts->copy()->addHour()->format('Y-m-d H:i:s'),
                    'status' => $weekend->status,
                    'attendance_status' => $attendanceStatus,
                    'notes' => 'Misa de capilla para pruebas de alcance por comunidad.',
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ]
            );
        }

        $this->command?->info('Misas de prueba creadas exitosamente.');
    }
}
