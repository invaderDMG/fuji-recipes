# Fuji Recipes (web)

Aplicación Laravel local para consultar, filtrar, comparar y editar recetas de
simulación de película de Fujifilm. Ver `../PLAN_LARAVEL.md`.

## Requisitos

- PHP 8.4+ (se usa `Pdo\Sqlite` para abrir la BD de origen en solo lectura)
- Composer, Node/npm (solo para `composer run dev`)

## Comandos (`make`, desde la raíz `~/fuji-recipes`)

| Comando | Qué hace |
|---|---|
| `make` | Lista los comandos |
| `make setup` | Primera instalación: dependencias, `.env`, BD, sensores, usuario local y enlace de fotos |
| `make serve` | Abre la app en http://127.0.0.1:8000 (admite fotos de hasta 64 MB) |
| `make update` | Scrape incremental + import en simulación: muestra qué cambiaría |
| `make import-force` | Aplica la importación (copia la BD antes) |
| `make test` / `make check` | Tests / Pint + tests |
| `make migrate` | Copia la BD y aplica migraciones pendientes |
| `make backup` | Copia la BD a `web/storage/app/backups` |

Opciones: `make scrape SENSOR=x-trans-v` (un solo índice) y
`make import ONLY=fxw|mine` (un solo origen).

Usa `make serve` en lugar de `php artisan serve` o `composer run dev`: esos
arrancan PHP con su límite por defecto de 2 MB por subida y no podrías subir
tus fotos.

La interfaz es un panel de Filament servido en la raíz. No hay pantalla de
login: el middleware `LoginLocalUser` inicia sesión con el usuario que crea
`LocalUserSeeder` (`local@fuji-recipes.test` / `fuji-recipes`, en
`config/fuji.php`). Sirve solo en 127.0.0.1.

## Recetas nuevas: scrape incremental

`fxw_scraper.py` cachea cada post en `.fxw_cache/`. Cada ejecución descarga
los 9 índices y **solo los posts nuevos**; el resto se vuelve a leer de la
caché. `--refresh` vuelve a descargarlo todo: evítalo salvo necesidad.

```bash
make update          # scrape + diff
make import-force    # aplicar
```

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
- Las fotos de muestra de cada post (tabla `post_photos` del scraper) se
  añaden a sus recetas sin borrar nunca las existentes ni tocar las tuyas.
- `needs_review` marca simulaciones sin mapear, ajustes ambiguos y valores
  fuera de rango. El resumen agrupa las simulaciones sin mapear para ampliar
  los alias de `FilmSimulationNormalizer`.

## Configuración

| Variable | Uso |
|---|---|
| `FXW_SOURCE_DB` | Ruta absoluta a `fujixweekly.db`. Conexión `fxw_source`, **solo lectura** |
| `FXW_DEFAULT_CAMERA`, `FXW_DEFAULT_SENSOR` | Cámara y sensor mientras no se elija otra en «Mi cámara» (`X-T30 III`, `x-trans-v`) |

El código lee estos valores con `config('fuji.*')`, nunca con `env()`.

## Tests y estilo

```bash
./vendor/bin/pint
php artisan test
```

## Mi cámara

En «Mi cámara» (menú lateral) eliges tu modelo; su sensor decide qué recetas
son **compatibles**: las que Fuji X Weekly publica en el índice de ese sensor.

- El listado abre en la pestaña «Compatibles con mi …», con «Todas» y
  «No compatibles» al lado, y marca cada receta con un icono de cámara.
- La ficha avisa si la receta no figura para tu cámara; el comparador tiene
  una fila «Mi …».
- Las recetas similares filtran por tu sensor y las recetas nuevas lo traen
  preseleccionado.
- API: `GET /api/recipes?compatible=true|false` y el campo
  `compatible_with_my_camera`.

## Fotos

- **De muestra:** las del post original. Se guarda solo la URL (CDN de
  WordPress) y la app pide miniaturas de 480 px; no se descarga nada al
  scrapear.
- **Tuyas:** «Mis fotos» en la ficha de cada receta. Se guardan en
  `web/storage/app/public/photos/` (fuera de git y fuera de las copias de la
  BD: haz tu propia copia de esa carpeta).

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
