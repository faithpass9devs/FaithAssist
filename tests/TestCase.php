<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Se ejecuta antes de setUpTraits() (y por lo tanto antes de que
     * RefreshDatabase corra migrate:fresh). Si la app no quedó aislada en
     * SQLite en memoria, aborta la suite para no tocar una base real.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $this->ensureTestingEnvironmentIsIsolated();
    }

    private function ensureTestingEnvironmentIsIsolated(): void
    {
        $problems = [];

        if ($this->app->configurationIsCached()) {
            $problems[] = 'existe bootstrap/cache/config.php (caché de configuración)';
        }

        if (! $this->app->environment('testing')) {
            $problems[] = "APP_ENV es '{$this->app->environment()}' en lugar de 'testing'";
        }

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            $problems[] = "la conexión de BD es '{$connection}' / '{$database}' en lugar de sqlite / :memory:";
        }

        if ($problems === []) {
            return;
        }

        throw new RuntimeException(
            'PRUEBAS ABORTADAS para proteger la base de datos: '.implode('; ', $problems).'. '
            .'Ejecuta "php artisan config:clear" y vuelve a correr las pruebas con "composer test".'
        );
    }
}
