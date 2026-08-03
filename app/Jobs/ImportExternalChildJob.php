<?php

namespace App\Jobs;

use App\Models\External\ExternalChild;
use App\Models\ExternalChildImport;
use App\Models\User;
use App\Services\Catechism\ExternalChildImportService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ImportExternalChildJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $externalChildId,
        public readonly int $userId,
    ) {}

    public function handle(ExternalChildImportService $service): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        if (ExternalChildImport::where('external_child_id', $this->externalChildId)->exists()) {
            return;
        }

        $externo = ExternalChild::find($this->externalChildId);

        if (! $externo) {
            return;
        }

        try {
            $service->import($user, $externo, $service->importDefaults($externo));
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 409) {
                return;
            }

            throw $e;
        }
    }
}
