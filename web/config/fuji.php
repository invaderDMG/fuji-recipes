<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base de datos de origen
    |--------------------------------------------------------------------------
    |
    | Ruta absoluta a fujixweekly.db, generada por los scripts de Python.
    | Se abre en modo solo lectura a través de la conexión "fxw_source".
    |
    */

    'source_db' => env('FXW_SOURCE_DB'),

    /*
    |--------------------------------------------------------------------------
    | Sensor por defecto
    |--------------------------------------------------------------------------
    |
    | Sensor de la cámara del usuario, usado como filtro por defecto.
    |
    */

    'default_sensor' => env('FXW_DEFAULT_SENSOR', 'x-trans-v'),

    /*
    |--------------------------------------------------------------------------
    | Copias de seguridad
    |--------------------------------------------------------------------------
    |
    | recipes:import copia la BD de Laravel aquí antes de escribir y conserva
    | las más recientes.
    |
    */

    'backups' => [
        'path' => storage_path('app/backups'),
        'keep' => 10,
    ],

];
