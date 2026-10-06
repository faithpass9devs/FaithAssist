<?php

namespace App\Jobs;

use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Catechism\ChildImportBatch;
use App\Models\Catechism\ChildLevelAssignment;
use App\Models\Operation\PeriodMovement;
use App\Models\User;
use App\Services\ChildCodeGenerator;
use App\Services\Catechism\ChildImportContext;
use App\Services\Catechism\ChildImportRowValidator;
use App\Services\Catechism\ChildImportService;
use App\Services\CatechismPeriodMovementService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportChildRowJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    /**
     * @param  array<int, int>  $levelIds
     */
    public function __construct(
        public readonly int $importBatchId,
        public readonly int $line,
        public readonly array $data,
        public readonly array $levelIds = [],
    ) {}

    public function handle(
        ChildImportService $imports,
        ChildImportRowValidator $validator,
        ChildCodeGenerator $codeGenerator,
        CatechismPeriodMovementService $movementService,
    ): void {
        $batch = ChildImportBatch::find($this->importBatchId);

        if (! $batch) {
            return;
        }

        $church = $batch->church()->first();

        if (! $church) {
            $imports->recordError($batch, $this->line, 'La parroquia del archivo ya no existe.');

            return;
        }

        $user = User::find($batch->user_id);

        try {
            $lada = ChildImportRowValidator::defaultLada();

            if ($lada === null) {
                throw new \RuntimeException('No hay una lada por defecto configurada en el catálogo de ladas.');
            }

            $result = $validator->validate($this->row(), ChildImportContext::make($church, $lada));

            if ($result['errors'] !== []) {
                $imports->recordError($batch, $this->line, implode(' ', $result['errors']));

                return;
            }

            $movement = $movementService->requireActiveMovementForChurch(
                $church,
                CatechismPeriodMovementService::INSCRIPTIONS
            );

            $this->createChild($codeGenerator, $result['data'], $result['level_ids'], $movement, $user?->id);

            $imports->recordImported($batch);
        } catch (ValidationException $e) {
            $imports->recordError($batch, $this->line, collect($e->errors())->flatten()->implode(' '));
        } catch (Throwable $e) {
            $imports->recordError($batch, $this->line, $e->getMessage() ?: 'Error desconocido al crear el niño.');
        }
    }

    private function createChild(
        ChildCodeGenerator $codeGenerator,
        array $data,
        array $levelIds,
        PeriodMovement $movement,
        ?int $userId,
    ): Child {
        return DB::transaction(function () use ($codeGenerator, $data, $levelIds, $movement, $userId): Child {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $payload = $data;
                $payload['code'] = $codeGenerator->generate($data);

                if ($userId !== null) {
                    $payload['created_by'] = $userId;
                    $payload['updated_by'] = $userId;
                }

                try {
                    $child = Child::query()->create($payload);

                    foreach ($levelIds as $levelId) {
                        ChildLevelAssignment::create([
                            'child_id' => $child->id,
                            'level_id' => $levelId,
                            'period_id' => $movement->period_id,
                            'period_movement_id' => $movement->id,
                            'status' => Status::ACTIVE,
                            'assigned_at' => now()->toDateString(),
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]);
                    }

                    return $child;
                } catch (QueryException $e) {
                    $sqlState = $e->errorInfo[0] ?? null;
                    $message = $e->getMessage();

                    $isUniqueViolation = in_array($sqlState, ['23000', '23505'], true)
                        && (str_contains($message, 'children.code') || str_contains($message, 'children_code_unique'));

                    if (! $isUniqueViolation) {
                        throw $e;
                    }
                }
            }

            throw new \RuntimeException('No se pudo generar un código único para el niño.');
        });
    }

    /**
     * Reconstruye la fila cruda del Excel a partir de los datos ya validados,
     * para que el job vuelva a pasarlos por el validador.
     */
    private function row(): array
    {
        return [
            'name' => $this->data['name'] ?? null,
            'paterno' => $this->data['paterno'] ?? null,
            'materno' => $this->data['materno'] ?? null,
            'birthdate' => $this->data['birthdate'] ?? null,
            'sex' => $this->data['sex'] ?? null,
            'blood_type' => $this->data['blood_type'] ?? null,
            'comunidad' => $this->data['community_id'] ?? null,
            'levels' => implode(',', $this->levelIds),
            'email' => $this->data['email'] ?? null,
            'phone' => $this->data['phone'] ?? null,
            'emergency_phone' => $this->data['emergency_phone'] ?? null,
        ];
    }
}
