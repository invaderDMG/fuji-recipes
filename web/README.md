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
- Borrar una receta en la app es un borrado lógico (`deleted_at`): se conserva
  (filtro «Borradas», se puede recuperar) y la importación la ignora, así que
  no se vuelve a crear. Tampoco se inserta por la API si su `source_url` es el
  de una receta borrada.
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

## API JSON

Sin autenticación y solo desde 127.0.0.1 (`OnlyLocalhost` devuelve 403 al
resto). Los errores siempre van en JSON, aunque el cliente no envíe `Accept`.

| Método | Ruta | Uso |
|---|---|---|
| GET | `/api/recipes` | Listado paginado con los mismos filtros que la UI |
| GET | `/api/recipes/{id}` | Una receta, con `as_text` en formato «Clave: valor» |
| GET | `/api/recipes/{id}/similar?n=&sensor=&same_sim=` | Las más cercanas, con distancia y desglose |
| POST | `/api/recipes` | Crea una receta propia (`source = manual`); 201 + `Location` |

Filtros de `GET /api/recipes` (las listas admiten `a,b` o `x[]=a&x[]=b`):
`q`, `simulation`, `family`, `dr`, `sensor`, `source`, `cce`, `fx_blue`,
`grain`, `wb_mode`, `tag`, `highlight_min/max`, `shadow_min/max`,
`color_min/max`, `favorite`, `needs_review`, `locked`, `missing`
(`true/false/1/0`), `sort` (`name`, `-published_at`…), `per_page` (≤ 100).

```bash
curl -s 'http://127.0.0.1:8000/api/recipes?sensor=x-trans-v&simulation=Classic%20Negative&highlight_max=-1'
curl -s 'http://127.0.0.1:8000/api/recipes/367/similar?n=5&sensor=x-trans-v'
```

Crear una receta (p. ej. desde `fxw_import.py`, en lugar de escribir en
`fujixweekly.db`). Los valores van normalizados: enums como en la UI
(`"Classic Negative"`, `"Weak"`, `"DR400"`), `wb_mode` canónico y números en
su rango; lo que no esté en las reglas se ignora.

```python
import json, urllib.request

payload = {
    "name": "Mi Gold", "author": "Yo",
    "film_simulation": "Classic Negative", "dynamic_range": "DR400",
    "grain_roughness": "Weak", "grain_size": "Small",
    "color_chrome_effect": "Strong", "color_chrome_fx_blue": "Off",
    "wb_mode": "Daylight", "wb_shift_red": 2, "wb_shift_blue": -4,
    "highlight": -1, "shadow": 0.5, "color": 3,
    "sharpness": -2, "high_iso_nr": -4, "clarity": -2,
    "sensors": ["x-trans-v"], "tags": ["propia"],
}
req = urllib.request.Request("http://127.0.0.1:8000/api/recipes",
                             data=json.dumps(payload).encode(),
                             headers={"Content-Type": "application/json"})
print(urllib.request.urlopen(req).status)  # 201; 422 con los errores si algo no valida
```
