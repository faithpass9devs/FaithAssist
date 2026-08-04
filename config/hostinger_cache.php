<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Caché de datos externos (Hostinger)
    |--------------------------------------------------------------------------
    |
    | Prefijo y tag usados para cachear las consultas a la BD remota `hostinger`
    | (listado paginado y opciones de filtro del módulo Externos). Requiere un
    | driver que soporte tags (Redis o Memcached).
    |
    */

    'prefix' => 'hostinger',

    'tags' => ['hostinger'],

    'ttl' => [
        // Dataset completo de externos por scope (segundos). Los datos de la BD
        // remota de Hostinger no se modifican, así que 24 horas es seguro.
        'all' => (int) env('HOSTINGER_CACHE_TTL_ALL', 86400),
        // Opciones de filtro: comunidades y niveles
        'filters' => (int) env('HOSTINGER_CACHE_TTL_FILTERS', 86400),
    ],

];
