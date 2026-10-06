<?php

namespace Database\Seeders;

use App\Models\Ecclesiastes\Church;
use App\Models\Operation\PeriodMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seeder de mantenimiento: asigna todos los movimientos de los periodos
 * existentes a la parroquia de Coatepec (producción solo tiene Coatepec).
 * Reasigna cualquier movimiento de otra parroquia a Coatepec y elimina los
 * duplicados que quedaran dentro del mismo periodo y tipo de movimiento.
 */
class AssignPeriodsToCoatepecSeeder extends Seeder
{
    public function run(): void
    {
        $coatepec = Church::query()
            ->whereHas('municipality', fn ($q) => $q->where('name', 'Coatepec Harinas'))
            ->first() ?? Church::query()->orderBy('id')->first();

        if (! $coatepec) {
            $this->command?->warn('No se encontró la parroquia de Coatepec. Nada que reasignar.');

            return;
        }

        DB::transaction(function () use ($coatepec) {
            $assigned = 0;
            $removed = 0;

            $movements = PeriodMovement::query()
                ->where('church_id', '!=', $coatepec->id)
                ->get(['id', 'period_id', 'period_movement_type_id']);

            foreach ($movements as $movement) {
                $alreadyInCoatepec = PeriodMovement::query()
                    ->where('period_id', $movement->period_id)
                    ->where('period_movement_type_id', $movement->period_movement_type_id)
                    ->where('church_id', $coatepec->id)
                    ->exists();

                if ($alreadyInCoatepec) {
                    $movement->delete();
                    $removed++;
                } else {
                    $movement->update(['church_id' => $coatepec->id]);
                    $assigned++;
                }
            }

            $this->command?->info(sprintf(
                'Movimientos reasignados a %s: %d asignados, %d duplicados eliminados.',
                $coatepec->name,
                $assigned,
                $removed,
            ));

            Log::info('[AssignPeriodsToCoatepecSeeder]', [
                'church_id' => $coatepec->id,
                'assigned' => $assigned,
                'removed' => $removed,
            ]);
        });
    }
}