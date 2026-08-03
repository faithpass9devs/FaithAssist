<?php

namespace App\Services\Catechism;

use App\Globals\BloodType;
use App\Globals\Status;
use App\Jobs\ImportExternalChildJob;
use App\Models\Catechism\Child;
use App\Models\External\ExternalChild;
use App\Models\ExternalChildImport;
use App\Models\ExternalChildImportBatch;
use App\Models\User;
use App\Repositories\Catechism\ExternosRepository;
use Illuminate\Support\Facades\Bus;

class ExternosService
{
    public function __construct(
        private readonly ExternosRepository $externos,
        private readonly ExternalChildImportService $importer,
    ) {}

    public function indexData(
        User $user,
        string $search,
        ?int $levelId = null,
        ?int $communityId = null
    ): array {
        $externos = $this->externos->paginateWithFilters($user, $search, $levelId, $communityId);

        $filterOptions = $this->externos->getFilterOptions($user);

        $importedIds = ExternalChildImport::query()
            ->whereIn('external_child_id', collect($externos->items())->pluck('id'))
            ->pluck('external_child_id')
            ->all();
        $importedSet = array_fill_keys($importedIds, true);

        return [
            'externos' => $externos->through(
                fn (ExternalChild $child) => $this->externos->serializeExternalChild(
                    $child,
                    isset($importedSet[$child->id])
                )
            ),
            'search' => $search,
            'filters' => [
                'level_id' => $levelId,
                'community_id' => $communityId,
            ],
            'communities' => $filterOptions['communities'],
            'levels' => $filterOptions['levels'],
            'sexLabels' => $this->sexLabels(),
            'levelStatusLabels' => $this->levelStatusLabels(),
            'latestImportBatch' => $this->latestImportBatch($user),
        ];
    }

    /**
     * @return array{batch_id:?string, total:int}
     */
    public function createImportBatch(
        User $user,
        string $search,
        ?int $levelId = null,
        ?int $communityId = null
    ): array {
        $ids = $this->externos->externosForBulkImport($user, $search, $levelId, $communityId);

        if ($ids->isEmpty()) {
            return ['batch_id' => null, 'total' => 0];
        }

        $jobs = $ids->map(
            fn (int $id): ImportExternalChildJob => new ImportExternalChildJob($id, $user->id)
        )->all();

        $batch = Bus::batch($jobs)
            ->name('Importación externos')
            ->allowFailures()
            ->dispatch();

        ExternalChildImportBatch::create([
            'batch_id' => $batch->id,
            'user_id' => $user->id,
            'filters' => [
                'search' => $search,
                'level_id' => $levelId,
                'community_id' => $communityId,
            ],
            'total_jobs' => $ids->count(),
        ]);

        return ['batch_id' => $batch->id, 'total' => $ids->count()];
    }

    public function batchStatus(User $user, string $batchId): ?array
    {
        $record = ExternalChildImportBatch::query()
            ->where('batch_id', $batchId)
            ->where('user_id', $user->id)
            ->first();

        if (! $record) {
            return null;
        }

        return $this->serializeBatch($record);
    }

    public function latestImportBatch(User $user): ?array
    {
        $record = ExternalChildImportBatch::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        return $record ? $this->serializeBatch($record) : null;
    }

    private function serializeBatch(ExternalChildImportBatch $record): array
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
                'filters' => $record->filters,
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
            'filters' => $record->filters,
            'created_at' => $record->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function showData(User $user, ExternalChild $child): array
    {
        return [
            'externo' => $this->externos->serializeExternalChild(
                $child,
                ExternalChildImport::where('external_child_id', $child->id)->exists()
            ),
        ];
    }

    public function registerData(User $user, ExternalChild $externo): array
    {
        $churches = $this->externos->getChurches($user);

        $defaultChurchId = collect($churches)->firstWhere('id', $externo->church_id)
            ? $externo->church_id
            : (collect($churches)->first()['id'] ?? null);

        $levels = $this->importer->resolveLevelsForExterno($externo);

        return [
            'externo' => $this->externos->serializeExternalChild(
                $externo,
                ExternalChildImport::where('external_child_id', $externo->id)->exists()
            ),
            'churches' => $churches,
            'municipalities' => $this->externos->getMunicipalities($user),
            'communities' => $this->externos->getCommunities($user),
            'defaultChurchId' => $defaultChurchId,
            'currentLevel' => $levels['currentLevel'],
            'targetLevel' => $levels['targetLevel'],
            'levels' => $this->externos->getLevels($user, $defaultChurchId),
            'bloodTypes' => $this->bloodTypeOptions(),
        ];
    }

    public function import(User $user, ExternalChild $externo, array $data): Child
    {
        return $this->importer->import($user, $externo, $data);
    }

    private function bloodTypeOptions(): array
    {
        return collect($this->bloodTypeLabels())
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    private function bloodTypeLabels(): array
    {
        return [
            BloodType::A_POSITIVE => 'A+',
            BloodType::A_NEGATIVE => 'A-',
            BloodType::B_POSITIVE => 'B+',
            BloodType::B_NEGATIVE => 'B-',
            BloodType::AB_POSITIVE => 'AB+',
            BloodType::AB_NEGATIVE => 'AB-',
            BloodType::O_POSITIVE => 'O+',
            BloodType::O_NEGATIVE => 'O-',
            BloodType::UNKNOWN => 'Desconocido',
        ];
    }

    private function sexLabels(): array
    {
        return [
            'H' => 'Hombre',
            'M' => 'Mujer',
        ];
    }

    private function levelStatusLabels(): array
    {
        return [
            Status::IN_PROGRESS => 'En progreso',
            Status::COMPLETED => 'Completado',
            Status::WITHDRAW => 'Retirado',
        ];
    }
}
