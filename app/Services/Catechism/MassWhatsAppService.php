<?php

namespace App\Services\Catechism;

use App\Jobs\SendMassWhatsAppToImportedChildJob;
use App\Models\Catechism\Child;
use App\Models\FailedWhatsappChild;
use App\Models\User;
use App\Models\WhatsappMassBatch;
use App\Services\UserScopeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

class MassWhatsAppService
{
    public function createBatch(
        User $user,
        string $search = '',
        ?int $churchId = null,
        ?int $municipalityId = null,
        ?int $communityId = null,
        ?int $levelId = null,
        ?string $status = null,
    ): array {
        $activeBatch = WhatsappMassBatch::query()
            ->where('user_id', $user->id)
            ->whereNull('batch_id')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->exists();

        if ($activeBatch) {
            return ['error' => 'Ya hay un envio masivo en proceso. Espera a que termine.'];
        }

        $runningBatch = WhatsappMassBatch::query()
            ->where('user_id', $user->id)
            ->whereNotNull('batch_id')
            ->latest('id')
            ->first();

        if ($runningBatch && ! Bus::findBatch($runningBatch->batch_id)?->finished()) {
            return ['error' => 'Ya hay un envio masivo en curso. Espera a que termine.'];
        }

        $children = $this->getImportedChildrenWithPhone(
            $user,
            $search,
            $churchId,
            $municipalityId,
            $communityId,
            $levelId,
            $status,
        );

        if ($children->isEmpty()) {
            return ['batch_id' => null, 'total' => 0];
        }

        $record = WhatsappMassBatch::create([
            'user_id' => $user->id,
            'total_jobs' => $children->count(),
            'filters' => array_filter([
                'search' => $search ?: null,
                'church_id' => $churchId,
                'municipality_id' => $municipalityId,
                'community_id' => $communityId,
                'level_id' => $levelId,
                'status' => $status,
            ], fn ($v) => $v !== null),
        ]);

        try {
            $jobs = $children->map(
                fn (Child $child): SendMassWhatsAppToImportedChildJob => new SendMassWhatsAppToImportedChildJob($child->id, $record->id)
            )->all();

            $batch = Bus::batch($jobs)
                ->name('WhatsApp masivo a importados')
                ->allowFailures()
                ->onQueue('whatsapp')
                ->dispatch();

            $record->update(['batch_id' => $batch->id]);
        } catch (\Throwable $e) {
            $record->delete();

            throw $e;
        }

        return ['batch_id' => $batch->id, 'total' => $children->count()];
    }

    public function batchStatus(User $user, string $batchId): ?array
    {
        $record = WhatsappMassBatch::query()
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
        $record = WhatsappMassBatch::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        return $record ? $this->serializeBatch($record) : null;
    }

    public function dismissBatch(User $user): void
    {
        $record = WhatsappMassBatch::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (! $record) {
            return;
        }

        if ($record->batch_id) {
            $batch = Bus::findBatch($record->batch_id);

            if ($batch && ! $batch->finished() && ! $batch->cancelled()) {
                $batch->cancel();
            }
        }

        $record->delete();
    }

    private function serializeBatch(WhatsappMassBatch $record): array
    {
        if (! $record->batch_id) {
            $stale = $record->created_at && $record->created_at->diffInMinutes(now()) >= 120;

            return [
                'batch_id' => null,
                'total' => $record->total_jobs,
                'processed' => $stale ? $record->total_jobs : 0,
                'pending' => $stale ? 0 : $record->total_jobs,
                'failed' => 0,
                'progress' => $stale ? 100 : 0,
                'finished' => $stale,
                'cancelled' => false,
                'created_at' => $record->created_at?->format('d/m/Y H:i'),
            ];
        }

        $batch = Bus::findBatch($record->batch_id);

        if ($batch === null) {
            return [
                'batch_id' => $record->batch_id,
                'total' => $record->total_jobs,
                'processed' => 0,
                'pending' => 0,
                'failed' => 0,
                'progress' => 0,
                'finished' => true,
                'cancelled' => false,
                'created_at' => $record->created_at?->format('d/m/Y H:i'),
            ];
        }

        $processed = max(0, $batch->totalJobs - $batch->pendingJobs);
        $finished = $batch->finished();
        $cancelled = $batch->cancelled();

        if (! $finished && ! $cancelled && $record->created_at && $record->created_at->diffInMinutes(now()) >= 120) {
            $finished = true;
        }

        return [
            'batch_id' => $record->batch_id,
            'total' => $batch->totalJobs,
            'processed' => $processed,
            'pending' => $finished ? 0 : $batch->pendingJobs,
            'failed' => $batch->failedJobs,
            'progress' => $batch->totalJobs > 0
                ? (int) round(($processed / $batch->totalJobs) * 100)
                : 0,
            'finished' => $finished,
            'cancelled' => $cancelled,
            'created_at' => $record->created_at?->format('d/m/Y H:i'),
            'failed_children' => $this->getFailedChildren($record->id),
        ];
    }

    private function getImportedChildrenWithPhone(
        User $user,
        string $search = '',
        ?int $churchId = null,
        ?int $municipalityId = null,
        ?int $communityId = null,
        ?int $levelId = null,
        ?string $status = null,
    ): Collection {
        $scope = new UserScopeService($user);

        $query = Child::query()
            ->where('origin', 'imported')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotNull('phone_lada')
            ->where('phone_lada', '!=', '')
            ->whereNull('deleted_at')
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($builder) use ($search): void {
                    $builder
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('paterno', 'like', "%{$search}%")
                        ->orWhere('materno', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($churchId, fn ($q) => $q->where('church_id', $churchId))
            ->when($communityId, fn ($q) => $q->where('community_id', $communityId))
            ->when($levelId, fn ($q) => $q->whereHas(
                'activeLevelAssignments',
                fn ($assignment) => $assignment->where('level_id', $levelId),
            ))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($municipalityId, function ($q) use ($municipalityId): void {
                $q->where(function ($builder) use ($municipalityId): void {
                    $builder->whereHas('church', fn ($church) => $church->where('municipality_id', $municipalityId))
                        ->orWhereHas('community', fn ($community) => $community->where('municipality_id', $municipalityId));
                });
            });

        if (! $scope->isGlobal()) {
            $query->where(function ($q) use ($scope): void {
                $q->whereIn('church_id', $scope->churchIds())
                    ->orWhereIn('community_id', $scope->communityIds());
            });
        }

        return $query->get(['id']);
    }

    private function getFailedChildren(?int $recordId): array
    {
        if (! $recordId) {
            return [];
        }

        return FailedWhatsappChild::query()
            ->where('batch_id', $recordId)
            ->with('child:id,name,paterno,materno,code')
            ->get()
            ->map(fn (FailedWhatsappChild $fail): array => [
                'child_id' => $fail->child_id,
                'name' => $fail->child?->full_name ?? 'Desconocido',
                'code' => $fail->child?->code ?? '—',
                'error' => $fail->error_message,
                'created_at' => $fail->created_at?->format('d/m/Y H:i'),
            ])
            ->all();
    }
}
