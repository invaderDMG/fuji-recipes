# Fuji Recipes (web)

Aplicación Laravel local para consultar, filtrar, comparar y editar recetas de
simulación de película de Fujifilm. Ver `../PLAN_LARAVEL.md`.

## Requisitos

- PHP 8.4+ (se usa `Pdo\Sqlite` para abrir la BD de origen en solo lectura)
- Composer, Node/npm (solo para `composer run dev`)

## Puesta en marcha

```bash
composer install
cp .env.example .env        # y ajusta FXW_SOURCE_DB con la ruta absoluta
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
```

## Arrancar

```bash
php artisan serve           # http://127.0.0.1:8000
composer run dev            # servidor + cola + logs + vite
```

## Configuración

| Variable | Uso |
|---|---|
| `FXW_SOURCE_DB` | Ruta absoluta a `fujixweekly.db`. Conexión `fxw_source`, **solo lectura** |
| `FXW_DEFAULT_SENSOR` | Sensor de la cámara del usuario (por defecto `x-trans-v`) |

El código lee estos valores con `config('fuji.*')`, nunca con `env()`.

## Tests y estilo

```bash
./vendor/bin/pint
php artisan test
```
