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

        // Se usa la conexión ya resuelta (no la config cruda) porque un DB_URL
        // exportado en el entorno puede sobrescribir driver y base de datos.
        // La conexión se crea de forma perezosa: aquí no se abre ningún PDO.
        $connection = $this->app['db']->connection();
        $driver = $connection->getConfig('driver');
        $database = $connection->getConfig('database');

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            $problems[] = "la conexión de BD es '{$driver}' / '{$database}' en lugar de sqlite / :memory:";
        }

        if ($problems === []) {
            return;
        }

        throw new RuntimeException(
            'PRUEBAS ABORTADAS para proteger la base de datos: '.implode('; ', $problems).'. '
            .'Ejecuta "php artisan config:clear", revisa que no haya variables DB_URL / DB_CONNECTION / DB_DATABASE '
            .'exportadas en tu terminal y vuelve a correr las pruebas con "composer test".'
        );
    }
}
