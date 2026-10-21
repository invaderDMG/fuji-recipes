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
    | Cámara por defecto
    |--------------------------------------------------------------------------
    |
    | Se usan mientras no se configure «Mi cámara» en la app (App\Support\MyCamera).
    |
    */

    'default_camera' => env('FXW_DEFAULT_CAMERA', 'X-T30 III'),

    'default_sensor' => env('FXW_DEFAULT_SENSOR', 'x-trans-v'),

    /*
    |--------------------------------------------------------------------------
    | Recetas desde captura (OCR)
    |--------------------------------------------------------------------------
    |
    | El OCR y el lector de recetas son los de fxw_import.py: la app los llama
    | con el Python del entorno virtual del proyecto. En macOS usa Vision
    | (pyobjc-framework-Vision: make setup-python); en Linux, Tesseract
    | (apt install tesseract-ocr).
    |
    */

    'capture' => [
        'python' => env('FXW_PYTHON', base_path('../.venv/bin/python')),
        'scripts_dir' => env('FXW_SCRIPTS_DIR', base_path('..')),
        'timeout' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Copias de seguridad
    |--------------------------------------------------------------------------
    |
    | recipes:import copia la BD de Laravel aquí antes de escribir y conserva
    | las más recientes.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Similitud
    |--------------------------------------------------------------------------
    |
    | Penalizaciones y pesos de RecipeSimilarity, portados de fxw_mine.py.
    | Si un campo falta en una sola de las dos recetas, suma peso × missing_factor.
    |
    */

    'similarity' => [
        'simulation' => [
            'same_family' => 2.0,
            'different' => 6.0,
        ],
        'white_balance' => [
            'missing' => 0.5,
            'kelvin_step' => 500,
            'kelvin_max' => 3.0,
            'both_auto' => 0.5,
            'different' => 1.5,
        ],
        'missing_factor' => 0.5,
        // Peso por unidad de diferencia
        'weights' => [
            'highlight' => 1.0,
            'shadow' => 1.0,
            'color' => 1.0,
            'clarity' => 0.4,
            'sharpness' => 0.2,
            'high_iso_nr' => 0.1,
            'wb_shift_red' => 0.5,
            'wb_shift_blue' => 0.5,
            'dynamic_range' => 0.75,
            'grain_roughness' => 0.3,
            'grain_size' => 0.2,
            'color_chrome_effect' => 0.5,
            'color_chrome_fx_blue' => 0.5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Usuario y acceso
    |--------------------------------------------------------------------------
    |
    | Filament necesita un usuario: LocalUserSeeder lo crea, sin contraseña.
    | En los entornos de auto_login_environments, LoginLocalUser inicia sesión
    | con él automáticamente. En el resto (producción) se entra con un enlace
    | de un solo uso que se envía a login_email (App\Auth\MagicLink).
    |
    */

    'local_user' => [
        'email' => 'local@fuji-recipes.test',
    ],

    'auto_login_environments' => ['local', 'staging', 'testing'],

    'login_email' => env('FUJI_LOGIN_EMAIL'),

    'magic_link_minutes' => 15,

    'backups' => [
        'path' => storage_path('app/backups'),
        'keep' => 10,
    ],

];
