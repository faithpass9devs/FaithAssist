<?php

namespace App\Services\Catechism;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Operation\Level;
use App\Models\Regions\Community;

/**
 * Catálogos resueltos una sola vez para validar todas las filas de un archivo,
 * de modo que la pre-validación no haga N+1 queries.
 */
final class ChildImportContext
{
    /**
     * @param  array<int, string>  $communities  id => nombre (municipio de la parroquia, activas)
     * @param  array<int, string>  $levels  id => nombre (diócesis de la parroquia, activos)
     */
    private function __construct(
        public readonly Church $church,
        public readonly string $lada,
        public readonly array $communities,
        public readonly array $levels,
    ) {
    }

    public static function make(Church $church, string $lada): self
    {
        $church->loadMissing('deanery:id,diocese_id');

        $communities = Community::query()
            ->where('status', Status::ACTIVE)
            ->when(
                $church->municipality_id,
                fn ($query) => $query->where('municipality_id', $church->municipality_id),
            )
            ->pluck('name', 'id')
            ->all();

        $dioceseId = $church->deanery?->diocese_id;

        $levels = Level::query()
            ->where('status', Status::ACTIVE)
            ->when($dioceseId, fn ($query) => $query->where('diocese_id', $dioceseId))
            ->pluck('name', 'id')
            ->all();

        return new self($church, $lada, $communities, $levels);
    }
}
