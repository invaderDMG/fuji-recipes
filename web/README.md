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
php artisan migrate --seed   # sensores y usuario local
php artisan recipes:import   # dry-run: revisa el diff
php artisan recipes:import --force
npm install
```

## Arrancar

```bash
php artisan serve           # http://127.0.0.1:8000
composer run dev            # servidor + cola + logs + vite
```

La interfaz es un panel de Filament servido en la raíz. No hay pantalla de
login: el middleware `LoginLocalUser` inicia sesión con el usuario que crea
`LocalUserSeeder` (`local@fuji-recipes.test` / `fuji-recipes`, en
`config/fuji.php`). Sirve solo en 127.0.0.1.

## Importación

`php artisan recipes:import` lee `fujixweekly.db` en solo lectura y por defecto
**solo muestra el diff**. Con `--force` copia antes la BD a
`storage/app/backups/` (conserva 10) y escribe. `--only=fxw|mine` limita el origen.

- Los campos editados a mano en recetas importadas quedan en `locked_fields` y
  la importación no los sobrescribe. "Restaurar valores de origen" en la ficha
  quita los bloqueos y recarga la receta.
- Las recetas que desaparecen del origen no se borran: se marcan con
  `missing_from_source_at`.
- `needs_review` marca simulaciones sin mapear, ajustes ambiguos y valores
  fuera de rango. El resumen agrupa las simulaciones sin mapear para ampliar
  los alias de `FilmSimulationNormalizer`.

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

## Cómo añadir un ajuste nuevo de la cámara

1. Migración con la columna nueva en `recipes`.
2. Caso o enum en `app/Enums/` si el ajuste tiene valores cerrados.
3. Cast en `Recipe::casts()` y, si es un ajuste, añadirlo a `Recipe::SETTINGS`
   (y a `Recipe::RANGES` si es numérico con rango).
4. Etiqueta en `App\Support\RecipeFields`, campo en `RecipeForm`, entrada en
   `RecipeInfolist` y columna o filtro en `RecipesTable`.
5. Mapeo en `SourceRecipeMapper` desde la fila de origen / `raw_json`.
6. Si debe pesar en la similitud: entrada en `config/fuji.php`
   (`similarity.weights`), su valor en `RecipeSimilarity::features()` y un test.

Para un metadato personal con valores libres y repetibles, mejor una etiqueta
que una columna nueva.
