# Fuji Recipes (web)

Aplicación Laravel para consultar, filtrar, comparar y editar recetas de
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
| `make deploy` / `make pull` | Push y actualización del droplet / baja su BD y tus fotos (ver «Droplet») |

Opciones: `make scrape SENSOR=x-trans-v` (un solo índice) y
`make import ONLY=fxw|mine` (un solo origen).

Usa `make serve` en lugar de `php artisan serve` o `composer run dev`: esos
arrancan PHP con su límite por defecto de 2 MB por subida y no podrías subir
tus fotos.

La interfaz es un panel de Filament servido en la raíz.

## Acceso

No se guardan contraseñas (`users.password` es nulo). Hay un solo usuario,
`local@fuji-recipes.test`, que crea `LocalUserSeeder`.

- **Local y preproducción** (`APP_ENV=local|staging`, y en los tests): no hay
  login. `LoginLocalUser` inicia sesión sola.
- **Producción:** la pantalla de login pide un correo. Si es el de
  `FUJI_LOGIN_EMAIL`, envía un enlace (`/login/enlace/{token}`) que sirve una
  vez y caduca a los 15 minutos (`fuji.magic_link_minutes`). La respuesta es
  la misma con cualquier correo. En la caché solo se guarda el hash del
  token. La sesión dura lo que diga `SESSION_LIFETIME` (30 días en la
  plantilla del droplet).

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
- Revisión: pestaña «Por revisar» del listado y sección en la ficha. Cada
  receta pendiente muestra el texto original y, si el origen da un valor por
  sensor («Strong (X-Trans IV), Weak (X-Trans V)»), la **propuesta para tu
  cámara**. «Aceptar propuesta» y «Dar por buena» bloquean el campo, así que
  la importación no la vuelve a marcar. «Off/NA», «Off or N/A» y similares
  se importan como Off.
- `needs_review` marca simulaciones sin mapear, ajustes ambiguos y valores
  fuera de rango. El resumen agrupa las simulaciones sin mapear para ampliar
  los alias de `FilmSimulationNormalizer`.

## Configuración

| Variable | Uso |
|---|---|
| `FXW_SOURCE_DB` | Ruta absoluta a `fujixweekly.db`. Conexión `fxw_source`, **solo lectura** |
| `FUJI_LOGIN_EMAIL` | Único correo que recibe el enlace de acceso en producción |
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

## Valoración y comentarios

Cada receta tiene una **valoración de 0 a 10** (0 es una nota; vacío = sin
valorar) y **comentarios** (columna `notes`, incluida en la búsqueda). Se
editan desde «Valorar» en la ficha o en el formulario, no bloquean nada
frente a la importación y hay filtro y orden por valoración (API:
`rating_min`, `rating_max`, `sort=-rating`).

## Recetas desde captura

En el listado, **«Desde captura»**: subes una captura con la receta escrita
(o pegas el texto) y se abre el formulario de nueva receta relleno, con el
sensor de tu cámara, los campos dudosos, el texto leído y un aviso si ya
tienes una receta idéntica (además de las 3 más parecidas). La captura no se
guarda.

El OCR y el lector son los de `fxw_import.py`, sin modificar: la app los
llama con `resources/python/recipe_from_capture.py` usando `.venv`. En macOS
el OCR es Vision (`make setup-python` instala `pyobjc-framework-Vision`) y
tarda unos 8–10 s por captura. En Linux, `fxw_import.py` usa Tesseract
(`apt install tesseract-ocr`). Con las capturas de muestra lee lo mismo que
Vision, salvo cuando el texto está encima de la foto: ahí se le escapa algún
ajuste, que verás vacío o marcado en el formulario. Las capturas de muestra de la raíz (`IMG_*`) se
usan en los tests de OCR real y no van a git.

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

Sin autenticación y solo desde 127.0.0.1 (en el droplet, solo desde el propio droplet) (`OnlyLocalhost` devuelve 403 al
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

## Droplet (DigitalOcean)

La app vive en el droplet: la BD, las fotos, el scraper y la importación. El
código va por GitHub (invaderDMG/fuji-recipes): el droplet tiene un clon y `make deploy`
hace push desde el Mac y pull en el droplet. Si quieres trabajar en local,
bajas una copia de los datos con `make pull`. Todo con `DROPLET=fuji@tu-dominio`, o
`export DROPLET=...` en tu shell.

Ficheros en `deploy/`: `provision.sh` (instalación), `nginx.conf`,
`php-fpm.conf` (pool que corre como el usuario `fuji`, con subidas de 64 MB)
y `production.env.example`.

### Primera vez

1. Crea un droplet Ubuntu 24.04 con tu clave SSH y apunta un dominio (registro
   A) a su IP.
2. Prepara el servidor (PHP 8.4, Nginx, Tesseract, Composer, usuario `fuji`,
   clon del repo, firewall y HTTPS):

   ```bash
   ssh root@<ip>
   curl -fsSLO https://raw.githubusercontent.com/invaderDMG/fuji-recipes/main/deploy/provision.sh
   DOMAIN=fuji.tudominio.com EMAIL=tu@correo.com bash provision.sh
   ```

3. Desde el Mac, sube las dos BDs, tus fotos y la caché del scraper (se niega
   si el droplet ya tiene BD):

   ```bash
   make push-data DROPLET=fuji@fuji.tudominio.com
   ```

4. En el droplet, rellena `fuji-recipes/web/.env` (`APP_URL`, correo SMTP y
   `FUJI_LOGIN_EMAIL`) y ejecuta `cd fuji-recipes && make server-update`.

**Correo:** DigitalOcean bloquea los puertos SMTP 25, 465 y 587, así que
necesitas un proveedor que acepte el 2525. La plantilla trae Brevo, que es
gratis hasta 300 correos al día. Para probar el envío:
`php artisan tinker --execute "app(App\\Auth\\MagicLink::class)->send(config('fuji.login_email'))"`.

### Día a día

| Dónde | Comando | Qué hace |
|---|---|---|
| Mac | `make deploy` | `git push` de `main` (con el árbol limpio); el droplet hace `git pull` y `make server-update` |
| Mac | `make pull` | Copia la BD local y baja la del droplet, `fujixweekly.db` y tus fotos |
| Droplet | `make update` / `make import-force` | Lo de siempre, ahora en el servidor |
| Droplet | `make server-update` | `composer install`, copia de la BD, migraciones y cachés |

`deploy` no toca nunca los datos ni el `.env` del droplet: están fuera de git. `pull` sí
sustituye tu BD local (antes la copia en `storage/app/backups`).

Para que el droplet busque recetas nuevas solo, añade esta línea con
`crontab -e` como `fuji`:

```
0 6 * * 1 cd ~/fuji-recipes && make scrape import-force >> web/storage/logs/update.log 2>&1
```

Copias de seguridad: `recipes:import` y `server-update` copian la BD antes de
escribir, pero las copias se quedan en el mismo disco. Activa los backups del
droplet o baja copias con `make pull`.
