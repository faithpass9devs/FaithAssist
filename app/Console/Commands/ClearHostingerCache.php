<?php

namespace App\Console\Commands;

use App\Support\HostingerCache;
use Illuminate\Console\Command;

class ClearHostingerCache extends Command
{
    protected $signature = 'hostinger:cache:clear';

    protected $description = 'Limpia la caché de los datos externos de Hostinger';

    public function handle(): int
    {
        HostingerCache::flush();

        $this->info('Caché de Hostinger limpiada.');

        return self::SUCCESS;
    }
}
