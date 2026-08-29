<?php

namespace App\Console\Commands;

use App\Jobs\CombineChildPdfsJob;
use App\Jobs\ExportChildPdfJob;
use App\Models\Catechism\Child;
use Illuminate\Console\Command;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class TempTestBatchCommand extends Command
{
    protected $signature = 'temp:test-batch {count=2}';

    protected $description = 'Temp: dispatch a tiny child-PDF batch to test finally ordering';

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $batchId = 'artisan-test-'.uniqid();

        $ids = Child::limit($count)->pluck('id');

        $jobs = $ids->map(fn ($id) => new ExportChildPdfJob($id, $batchId))->all();

        Bus::batch($jobs)
            ->name('Temp test batch')
            ->allowFailures()
            ->finally(function (Batch $batch) use ($batchId): void {
                \Illuminate\Support\Facades\Storage::put('exports/markers/'.$batchId.'.json', json_encode([
                    'failed' => $batch->failedJobs,
                    'time' => now()->toDateTimeString(),
                ]));
                CombineChildPdfsJob::dispatch($batchId, $batch->failedJobs);
            })
            ->dispatch();

        $this->info('Dispatched batch: '.$batchId.' jobs='.count($jobs));

        return self::SUCCESS;
    }
}
