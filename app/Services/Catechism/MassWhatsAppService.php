<?php

namespace App\Services\Catechism;

use App\Jobs\SendMassWhatsAppToImportedChildJob;
use App\Models\Catechism\Child;
use App\Models\User;
use App\Models\WhatsappMassBatch;
use App\Repositories\Catechism\ExternosRepository;
use App\Services\UserScopeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

class MassWhatsAppService
{
    public function __construct(
        private readonly ExternosRepository $externos,
    ) {}

    public function createBatch(
        User $user,
        string $search = '',
        ?int $churchId = null,
        ?int $municipalityId = null,
        ?int $communityId = null,
        ?int $levelId = null,
        ?string $status = null,
    ): array {
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

        $jobs = $children->map(
            fn (Child $child): SendMassWhatsAppToImportedChildJob => new SendMassWhatsAppToImportedChildJob($child->id)
        )->all();

        $batch = Bus::batch($jobs)
            ->name('WhatsApp masivo a importados')
            ->allowFailures()
            ->dispatch();

        WhatsappMassBatch::create([
            'batch_id' => $batch->id,
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

    private function serializeBatch(WhatsappMassBatch $record): array
    {
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

        return [
            'batch_id' => $record->batch_id,
            'total' => $batch->totalJobs,
            'processed' => $processed,
            'pending' => $batch->pendingJobs,
            'failed' => $batch->failedJobs,
            'progress' => $batch->totalJobs > 0
                ? (int) round(($processed / $batch->totalJobs) * 100)
                : 0,
            'finished' => $batch->finished(),
            'cancelled' => $batch->cancelled(),
            'created_at' => $record->created_at?->format('d/m/Y H:i'),
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
}
