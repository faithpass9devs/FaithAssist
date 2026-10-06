<?php

namespace App\Services\Catechism;

use App\Imports\Catechism\ChildrenImport;
use App\Jobs\FinalizeChildImportJob;
use App\Jobs\ImportChildRowJob;
use App\Models\Catechism\ChildImportBatch;
use App\Models\Catechism\ChildImportError;
use App\Models\Ecclesiastes\Church;
use App\Models\User;
use App\Services\CatechismPeriodMovementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChildImportService
{
    public function __construct(
        private readonly ChildImportRowValidator $validator,
        private readonly CatechismPeriodMovementService $movementService,
    ) {
    }

    /**
     * Valida el archivo completo y encola un job por cada fila válida.
     *
     * @return array{batch_id: string, total: int, rejected: int}
     *
     * @throws ValidationException
     */
    public function createBatch(User $user, Church $church, UploadedFile $file): array
    {
        $lada = ChildImportRowValidator::defaultLada();

        if ($lada === null) {
            throw ValidationException::withMessages([
                'file' => 'No hay una lada por defecto configurada en el catálogo de ladas.',
            ]);
        }

        $movement = $this->movementService->requireActiveMovementForChurch(
            $church,
            CatechismPeriodMovementService::INSCRIPTIONS
        );

        $batchId = (string) Str::uuid();
        $directory = "imports/{$batchId}";

        if (! Storage::disk('local')->putFileAs($directory, $file, 'origen.xlsx')) {
            throw new \RuntimeException('No se pudo almacenar el archivo cargado.');
        }

        $path = Storage::disk('local')->path("{$directory}/origen.xlsx");

        ChildrenImport::assertHeadings(ChildrenImport::headings($path));

        $rows = ChildrenImport::rows($path);
        $context = ChildImportContext::make($church, $lada);

        $batch = ChildImportBatch::create([
            'batch_id' => $batchId,
            'user_id' => $user->id,
            'church_id' => $church->id,
            'period_id' => $movement->period_id,
            'period_movement_id' => $movement->id,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => "{$directory}/origen.xlsx",
            'total_jobs' => 0,
            'imported_count' => 0,
            'failed_count' => 0,
        ]);

        $jobs = [];
        $rejected = 0;

        foreach ($rows as $row) {
            $result = $this->validator->validate($row['raw'], $context);

            if ($result['errors'] !== []) {
                $batch->errors()->create([
                    'line' => $row['line'],
                    'message' => implode(' ', $result['errors']),
                ]);
                $rejected++;

                continue;
            }

            $jobs[] = new ImportChildRowJob($batch->id, $row['line'], $result['data'], $result['level_ids']);
        }

        $batch->forceFill([
            'total_jobs' => count($jobs),
            'failed_count' => $rejected,
        ])->save();

        $this->dispatch($batch, $jobs);

        return [
            'batch_id' => $batch->batch_id,
            'total' => count($jobs),
            'rejected' => $rejected,
        ];
    }

    public function status(User $user, string $batchId): ?array
    {
        $record = $this->findForUser($user, $batchId);

        if ($record === null) {
            return null;
        }

        $record->loadMissing(['church:id,name', 'period:id,name']);

        $busBatch = $record->queue_batch_id !== null
            ? Bus::findBatch($record->queue_batch_id)
            : null;
        $running = $busBatch !== null && ! $busBatch->finished();

        $processed = $running
            ? max(0, $busBatch->totalJobs - $busBatch->pendingJobs)
            : $record->total_jobs;

        $pending = $running ? $busBatch->pendingJobs : 0;
        $failed = $running ? $busBatch->failedJobs : $record->failed_count;

        $total = max($record->total_jobs, $processed + $pending + $failed);

        return [
            'batch_id' => $record->batch_id,
            'church' => $record->church?->name,
            'period' => $record->period?->name,
            'original_filename' => $record->original_filename,
            'total' => $total,
            'processed' => $processed,
            'pending' => $pending,
            'imported' => $record->imported_count,
            'failed' => $failed,
            'progress' => $total > 0 ? (int) round((($total - $pending) / $total) * 100) : 100,
            'finished' => ! $running,
            'cancelled' => (bool) $busBatch?->cancelled(),
            'has_errors' => ! empty($record->error_report_path) || $record->failed_count > 0,
        ];
    }

    public function latestBatch(User $user): ?array
    {
        $record = ChildImportBatch::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        return $record ? $this->status($user, $record->batch_id) : null;
    }

    public function errorReport(User $user, string $batchId): string
    {
        $record = $this->findForUser($user, $batchId);

        if ($record === null) {
            throw new NotFoundHttpException('Importación no encontrada.');
        }

        if (empty($record->error_report_path) || ! Storage::disk('local')->exists($record->error_report_path)) {
            throw new NotFoundHttpException('El reporte de errores aún no está listo.');
        }

        return $record->error_report_path;
    }

    public function findForUser(User $user, string $batchId): ?ChildImportBatch
    {
        $record = ChildImportBatch::query()->where('batch_id', $batchId)->first();

        if ($record === null) {
            return null;
        }

        if ((int) $record->user_id !== (int) $user->id && ! $user->hasRole('Superadmin')) {
            return null;
        }

        return $record;
    }

    public function recordError(ChildImportBatch $batch, int $line, string $message): void
    {
        ChildImportError::create([
            'child_import_batch_id' => $batch->id,
            'line' => $line,
            'message' => Str::limit($message, 500, ''),
        ]);

        $batch->increment('failed_count');
    }

    public function recordImported(ChildImportBatch $batch): void
    {
        $batch->increment('imported_count');
    }

    /**
     * @param  array<int, ImportChildRowJob>  $jobs
     */
    private function dispatch(ChildImportBatch $batch, array $jobs): void
    {
        $batchId = $batch->batch_id;

        if ($jobs === []) {
            FinalizeChildImportJob::dispatch($batchId);

            return;
        }

        $busBatch = Bus::batch($jobs)
            ->name("Importación niños: {$batch->original_filename}")
            ->allowFailures()
            ->finally(function () use ($batchId): void {
                FinalizeChildImportJob::dispatch($batchId);
            })
            ->dispatch();

        $batch->forceFill(['queue_batch_id' => $busBatch->id])->save();
    }
}
