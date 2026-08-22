<?php

namespace App\Services\Catechism;

use App\Jobs\SendMassWhatsAppToImportedChildJob;
use App\Models\Catechism\Child;
use App\Models\ChildWhatsappDelivery;
use App\Models\User;
use App\Models\WhatsappMassBatch;
use App\Models\WhatsappMessage;
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
        $this->purgeStaleBatches($user);

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

        $runningBusBatch = $runningBatch ? Bus::findBatch($runningBatch->batch_id) : null;

        if ($runningBusBatch && ! $runningBusBatch->finished()) {
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

            $this->createDeliveries($children, $record->id);
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
        $records = WhatsappMassBatch::query()
            ->where('user_id', $user->id)
            ->get();

        foreach ($records as $record) {
            if ($record->batch_id) {
                try {
                    $batch = Bus::findBatch($record->batch_id);

                    if ($batch && ! $batch->finished() && ! $batch->cancelled()) {
                        $batch->cancel();
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $this->cancelRecordDeliveries($record->id);
        }

        WhatsappMassBatch::query()
            ->where('user_id', $user->id)
            ->delete();
    }

    private function cancelRecordDeliveries(int $recordId): void
    {
        $queuedMessageIds = ChildWhatsappDelivery::query()
            ->where('whatsapp_mass_batch_id', $recordId)
            ->where('status', ChildWhatsappDelivery::STATUS_QUEUED)
            ->pluck('whatsapp_message_id')
            ->filter()
            ->values();

        if ($queuedMessageIds->isNotEmpty()) {
            WhatsappMessage::query()
                ->whereIn('id', $queuedMessageIds)
                ->where('status', WhatsappMessage::STATUS_PENDING)
                ->update([
                    'status' => WhatsappMessage::STATUS_FAILED,
                    'error_message' => 'Envío cancelado por el usuario.',
                ]);
        }

        ChildWhatsappDelivery::query()
            ->where('whatsapp_mass_batch_id', $recordId)
            ->where('status', ChildWhatsappDelivery::STATUS_QUEUED)
            ->update([
                'status' => ChildWhatsappDelivery::STATUS_FAILED,
                'error_message' => 'Envío cancelado por el usuario.',
            ]);
    }

    private function createDeliveries(Collection $children, int $recordId): void
    {
        $now = now();

        $rows = $children
            ->map(fn (Child $child): array => [
                'child_id' => $child->id,
                'whatsapp_mass_batch_id' => $recordId,
                'status' => ChildWhatsappDelivery::STATUS_QUEUED,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        ChildWhatsappDelivery::upsert(
            $rows,
            ['child_id', 'whatsapp_mass_batch_id'],
            ['status', 'updated_at'],
        );
    }

    private function purgeStaleBatches(User $user): void
    {
        $staleRecords = WhatsappMassBatch::query()
            ->where('user_id', $user->id)
            ->where('created_at', '<', now()->subMinutes(120))
            ->get();

        foreach ($staleRecords as $record) {
            if ($record->batch_id) {
                try {
                    $batch = Bus::findBatch($record->batch_id);

                    if ($batch && ! $batch->finished() && ! $batch->cancelled()) {
                        $batch->cancel();
                    }
                } catch (\Throwable) {
                    // batch already expired from driver
                }
            }
        }

        WhatsappMassBatch::query()
            ->where('user_id', $user->id)
            ->where('created_at', '<', now()->subMinutes(120))
            ->delete();
    }

    private function serializeBatch(WhatsappMassBatch $record): array
    {
        $counts = ChildWhatsappDelivery::query()
            ->where('whatsapp_mass_batch_id', $record->id)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sent = (int) ($counts[ChildWhatsappDelivery::STATUS_SENT] ?? 0);
        $failed = (int) ($counts[ChildWhatsappDelivery::STATUS_FAILED] ?? 0);
        $queued = (int) ($counts[ChildWhatsappDelivery::STATUS_QUEUED] ?? 0);

        $total = max($record->total_jobs, $sent + $failed + $queued);
        $processed = $sent + $failed;
        $cancelled = false;

        if ($record->batch_id) {
            try {
                $cancelled = (bool) Bus::findBatch($record->batch_id)?->cancelled();
            } catch (\Throwable) {
                $cancelled = false;
            }
        }

        $finished = ($total > 0 && $processed >= $total);

        if (! $finished && $record->created_at && $record->created_at->diffInMinutes(now()) >= 120) {
            $finished = true;
        }

        return [
            'batch_id' => $record->batch_id,
            'total' => $total,
            'sent' => $sent,
            'pending' => $finished ? 0 : $queued,
            'processed' => $processed,
            'failed' => $failed,
            'progress' => $total > 0 ? (int) round(($processed / $total) * 100) : 0,
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

        return ChildWhatsappDelivery::query()
            ->where('whatsapp_mass_batch_id', $recordId)
            ->where('status', ChildWhatsappDelivery::STATUS_FAILED)
            ->with('child:id,name,paterno,materno,code')
            ->get()
            ->map(fn (ChildWhatsappDelivery $fail): array => [
                'child_id' => $fail->child_id,
                'name' => trim(collect([$fail->child?->name, $fail->child?->paterno, $fail->child?->materno])->filter()->implode(' ')) ?: 'Desconocido',
                'code' => $fail->child?->code ?? '—',
                'error' => $fail->error_message,
                'created_at' => $fail->updated_at?->format('d/m/Y H:i'),
            ])
            ->all();
    }
}
