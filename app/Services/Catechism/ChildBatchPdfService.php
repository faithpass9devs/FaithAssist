<?php

namespace App\Services\Catechism;

use App\Jobs\CombineChildPdfsJob;
use App\Jobs\ExportChildPdfJob;
use App\Models\Catechism\Child;
use App\Models\Catechism\ChildPdfExportBatch;
use App\Models\User;
use App\Services\UserScopeService;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChildBatchPdfService
{
    /**
     * @return array{batch_id: string, total: int}
     */
    public function createBatch(
        User $user,
        string $search,
        ?int $churchId = null,
        ?int $municipalityId = null,
        ?int $communityId = null,
        ?int $levelId = null,
        ?string $status = null,
        ?string $origin = null
    ): array {
        $scope = new UserScopeService($user);

        $query = Child::query()
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->when($churchId, fn (Builder $query) => $query->where('church_id', $churchId))
            ->when($communityId, fn (Builder $query) => $query->where('community_id', $communityId))
            ->when($levelId, fn (Builder $query) => $query->whereHas(
                'activeLevelAssignments',
                fn (Builder $assignment) => $assignment->where('level_id', $levelId)
            ))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($origin, fn (Builder $query) => $query->where('origin', $origin))
            ->when($municipalityId, function (Builder $query) use ($municipalityId): void {
                $query->where(function (Builder $builder) use ($municipalityId): void {
                    $builder->whereHas('church', fn (Builder $church) => $church->where('municipality_id', $municipalityId))
                        ->orWhereHas('community', fn (Builder $community) => $community->where('municipality_id', $municipalityId));
                });
            })
            ->select('id')
            ->orderBy('paterno')
            ->orderBy('materno')
            ->orderBy('name');

        $ids = $scope->applyChildScope($query)->pluck('id');

        if ($ids->isEmpty()) {
            throw new NotFoundHttpException('No hay niños que coincidan con los filtros seleccionados.');
        }

        $filters = [
            'search' => $search,
            'church_id' => $churchId,
            'municipality_id' => $municipalityId,
            'community_id' => $communityId,
            'level_id' => $levelId,
            'status' => $status,
            'origin' => $origin,
        ];

        $batchId = (string) Str::uuid();

        $jobs = $ids->map(
            fn (int $id): ExportChildPdfJob => new ExportChildPdfJob($id, $batchId)
        )->all();

        ChildPdfExportBatch::create([
            'batch_id' => $batchId,
            'user_id' => $user->id,
            'filters' => $filters,
            'total_jobs' => count($jobs),
        ]);

        Bus::batch($jobs)
            ->name('Export PDF niños')
            ->allowFailures()
            ->finally(function (Batch $batch) use ($batchId): void {
                CombineChildPdfsJob::dispatch($batchId, $batch->failedJobs);
            })
            ->dispatch();

        return ['batch_id' => $batchId, 'total' => count($jobs)];
    }

    public function batchStatus(User $user, string $batchId): ?array
    {
        $record = ChildPdfExportBatch::query()
            ->where('batch_id', $batchId)
            ->where('user_id', $user->id)
            ->first();

        if (! $record) {
            return null;
        }

        return $this->serializeBatch($record);
    }

    public function latestBatch(User $user): ?array
    {
        $record = ChildPdfExportBatch::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        return $record ? $this->serializeBatch($record) : null;
    }

    private function serializeBatch(ChildPdfExportBatch $record): array
    {
        $batch = Bus::findBatch($record->batch_id);

        if ($batch !== null && ! $batch->finished()) {
            $processed = max(0, $batch->totalJobs - $batch->pendingJobs);
            $progress = $batch->totalJobs > 0
                ? (int) round(($processed / $batch->totalJobs) * 100)
                : 100;

            return [
                'batch_id' => $record->batch_id,
                'total' => $batch->totalJobs,
                'processed' => $processed,
                'pending' => $batch->pendingJobs,
                'failed' => $batch->failedJobs,
                'progress' => $progress,
                'finished' => false,
                'cancelled' => $batch->cancelled(),
                'has_result' => false,
                'filters' => $record->filters,
                'created_at' => $record->created_at?->format('d/m/Y H:i'),
            ];
        }

        return [
            'batch_id' => $record->batch_id,
            'total' => $record->total_jobs,
            'processed' => $record->total_jobs,
            'pending' => 0,
            'failed' => $record->failed_count,
            'progress' => 100,
            'finished' => true,
            'cancelled' => false,
            'has_result' => ! empty($record->result_storage_path),
            'filters' => $record->filters,
            'created_at' => $record->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function download(User $user, string $batchId): string
    {
        $record = ChildPdfExportBatch::query()
            ->where('batch_id', $batchId)
            ->where('user_id', $user->id)
            ->first();

        if (! $record) {
            throw new NotFoundHttpException('Exportación no encontrada.');
        }

        if (empty($record->result_storage_path) || ! Storage::exists($record->result_storage_path)) {
            throw new NotFoundHttpException('El PDF aún no está listo.');
        }

        $this->ensureAccess($user, $record);

        return $record->result_storage_path;
    }

    private function ensureAccess(User $user, ChildPdfExportBatch $record): void
    {
        if ((int) $record->user_id !== (int) $user->id && ! $user->hasRole('Superadmin')) {
            throw new AccessDeniedHttpException('No tienes permiso para descargar esta exportación.');
        }
    }
}
