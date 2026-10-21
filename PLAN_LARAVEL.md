# Fuji Recipes — aplicación Laravel local

Brief para Claude Code. Construye una aplicación Laravel **local y de uso personal** para consultar, filtrar, comparar y editar recetas de simulación de película de Fujifilm. Los datos vienen de una base SQLite que ya generan unos scripts de Python. Trabaja por fases (sección 12) y no empieces una fase hasta que la anterior cumpla sus criterios de aceptación.

---

## 1. Contexto

En `~/fuji-recipes/` (el directorio desde el que se lanza Claude Code) ya existen:

| Fichero | Qué es | ¿Lo toca Laravel? |
|---|---|---|
| `fxw_scraper.py` | Descarga las recetas de fujixweekly.com → `fujixweekly.db` | No. Nunca se modifica |
| `fxw_mine.py` | Recetas propias (`post_url = 'mine:<nombre>'`) + **algoritmo de similitud (implementación de referencia)** | Solo se lee, para portarlo |
| `fxw_import.py` | OCR de capturas/notas → receta propia en `fujixweekly.db` | No |
| `fujixweekly.db` | SQLite generada por los scripts | **Solo lectura** |
| `.fxw_cache/`, `.venv/`, `probe_*` | Caché, entorno virtual, sondeos de cámara | Ignorar |

El scraper **borra y reinserta** las recetas de cada post en cada ejecución (`DELETE … WHERE post_url=?` + `INSERT`), así que sus `id` no son estables y cualquier edición hecha en esa BD se pierde. De ahí la decisión principal de la sección 2.

### Esquema de origen (`fujixweekly.db`)

```sql
posts(url TEXT PK, title, published, image_url, author, source)   -- author/source: solo recetas propias
recipes(
  id INTEGER PK,                 -- NO estable entre ejecuciones
  post_url TEXT, block_index INTEGER,   -- clave natural: UNIQUE(post_url, block_index)
  name,
  film_simulation TEXT,          -- texto libre: "Classic Negative", "PRO Neg. Std", "Acros (+Y, +R or +G)"...
  dynamic_range TEXT,            -- "DR200", "DR400", "DR-Auto"
  d_range_priority TEXT,
  grain_roughness TEXT, grain_size TEXT,          -- "Weak"/"Strong"/"Off", "Small"/"Large"
  color_chrome_effect TEXT, color_chrome_fx_blue TEXT,
  white_balance TEXT,            -- texto original, p. ej. "Auto, +1 Red & -2 Blue"
  wb_mode TEXT, wb_kelvin INTEGER, wb_shift_red INTEGER, wb_shift_blue INTEGER,
  tone_curve TEXT,
  highlight REAL, shadow REAL, color REAL,
  monochromatic_color TEXT,
  sharpness REAL, high_iso_nr REAL, clarity REAL,
  smooth_skin TEXT,
  iso TEXT, iso_max INTEGER,
  exposure_compensation TEXT,
  raw_json TEXT                  -- todas las claves tal como aparecían en el post
)
recipe_sensors(post_url, sensor, index_label)   -- sensor: x-trans-v | x-trans-iv | x-trans-iii | x-trans-ii | x-trans-i | bayer | exr-cmos | gfx | full-spectrum | (propias: el que se indicó)
v_recipes  -- vista: recipes + post + sensors (group_concat)
```

Inspecciona la BD real antes de diseñar nada (`sqlite3 fujixweekly.db ".schema"`, recuentos, `SELECT DISTINCT film_simulation`). Hay unas 500 recetas, y los valores reales mandan sobre este documento.

---

## 2. Decisiones de arquitectura

1. **Laravel tiene su propia BD** (`web/database/database.sqlite`) y es el **sistema de registro**. `fujixweekly.db` es solo una fuente de staging que se importa con un comando Artisan.
2. **La importación es idempotente y por clave natural** (`source_url` + `block_index`). Nunca depende del `id` de origen.
3. **Las ediciones manuales tienen prioridad.** Cada campo que se edita a mano queda bloqueado (`locked_fields`) y la importación no lo sobrescribe.
4. **Nunca se borra nada por importar.** Si una receta desaparece del origen, se marca `missing_from_source_at`.
5. **La conexión a la BD de origen es de solo lectura por contrato.** Ningún código escribe en ella, y hay un test que lo verifica comparando el hash del fichero antes y después de importar.
6. **UI con Filament.** Las tablas, los filtros y los formularios se declaran en clases PHP, sin escribir Blade ni frontend.
7. **La similitud se porta 1:1 desde `fxw_mine.py`** y se valida con los tests dorados de la sección 7.

---

## 3. Stack

- **Laravel `^12.0`** (`composer create-project laravel/laravel:^12.0 web`) y PHP 8.2+.
- **SQLite**: sin Docker ni Sail. Se sirve con `composer run dev` o `php artisan serve`, solo en 127.0.0.1.
- **Filament**: la última versión estable compatible con Laravel 12. Compruébalo con Composer y no fijes una versión de memoria.
- **Pest** para los tests y **Pint** para el estilo.
- Sin autenticación: es una app local de un solo usuario. Si Filament exige un usuario, crea uno con un seeder y documenta las credenciales en el README de `web/`.
- No añadas otros paquetes sin preguntar antes.

---

## 4. Estructura de directorios

```
~/fuji-recipes/
├── PLAN_LARAVEL.md          ← este documento
├── fxw_*.py, fujixweekly.db ← existentes, no tocar
└── web/                     ← proyecto Laravel (lo creas tú)
```

Configuración en `web/.env`:

```
FXW_SOURCE_DB=/Users/<usuario>/fuji-recipes/fujixweekly.db   # ruta absoluta
FXW_DEFAULT_SENSOR=x-trans-v                                 # cámara del usuario: X-T30 III
```

Define la conexión `fxw_source` (driver sqlite) en `config/database.php` y lee ambos valores vía `config/fuji.php`, nunca con `env()` fuera de `config/`.

---

## 5. Modelo de datos (Laravel)

### Enums (PHP backed enums en `app/Enums/`)

- `FilmSimulation`: Provia, Velvia, Astia, ClassicChrome, RealaAce, ProNegHi, ProNegStd, ClassicNegative, NostalgicNeg, Eterna, EternaBleachBypass, Acros, AcrosYe, AcrosR, AcrosG, Monochrome, MonochromeYe, MonochromeR, MonochromeG, Sepia.
  - Método `family()`. Familias: `proneg` = ProNegHi, ProNegStd, RealaAce · `neg` = ClassicNegative, NostalgicNeg · `chrome` = ClassicChrome · `eterna` = Eterna, EternaBleachBypass · `slide` = Provia, Astia · `vivid` = Velvia · `mono` = Acros*, Monochrome*, Sepia.
  - Método `label()` con el nombre que muestra la cámara.
- `EffectLevel`: Off, Weak, Strong. Se usa para el grano (rugosidad), CCE, FX Blue y Smooth Skin.
- `GrainSize`: Small, Large.
- `DynamicRange`: Auto, DR100, DR200, DR400.

### Tablas

**`recipes`**
- Identidad: `id`, `slug` (único), `name`, `source` (`fxw` | `mine` | `manual`), `source_url` (nullable), `block_index` (default 0), índice único (`source_url`, `block_index`).
- Metadatos: `author`, `published_at`, `image_url`.
- Ajustes:
  - `film_simulation` (enum, nullable) y `film_simulation_raw`;
  - `dynamic_range`, `d_range_priority`;
  - `grain_roughness`, `grain_size`;
  - `color_chrome_effect`, `color_chrome_fx_blue`;
  - `wb_mode` (string: `Auto`, `Auto White Priority`, `Daylight`, `Shade`, `Fluorescent 1-3`, `Incandescent`, `Kelvin`…), `wb_kelvin`, `wb_shift_red`, `wb_shift_blue`, `white_balance_raw`;
  - `highlight`, `shadow`, `color`, `sharpness`, `high_iso_nr`, `clarity` (decimal 3,1, nullable);
  - `monochromatic_color`, `smooth_skin`, `tone_curve`;
  - `iso_raw`, `iso_max`, `exposure_compensation`.
- Datos del origen y del import: `raw_json` (json), `locked_fields` (json, default `[]`), `needs_review` (bool), `missing_from_source_at`, `imported_at`.
- Personales: `notes` (text), `rating` (tinyint 1–5, nullable), `is_favorite` (bool), `tested_at` (nullable).
- `timestamps`.

**`sensors`**: `slug`, `label`, `cameras` (texto). Seeder con los 9 slugs de origen.

**`recipe_sensor`**: pivot.

**`tags`** + **`recipe_tag`**: etiquetas libres ("nublado", "noche", "retrato"…).

**`collections`** + **`collection_recipe`** (con `position` 1–7): conjuntos de recetas pensados para los slots C1–C7 de la cámara. En esta fase solo se guardan; la transferencia a la cámara queda fuera de alcance.

Modelos Eloquent con `casts()` (enums, json, datetime, decimal) y relaciones. Factories para todos los modelos.

### Rangos válidos (validación de formularios)

| Campo | Rango |
|---|---|
| highlight, shadow | −2 … +4, paso 0,5 |
| color, sharpness, high_iso_nr | −4 … +4 (enteros) |
| clarity | −5 … +5 |
| wb_shift_red, wb_shift_blue | −9 … +9 |
| wb_kelvin | 2500 … 10000 |

Las recetas antiguas (X-Trans I/II) pueden tener valores fuera de estos rangos. Al importar, **no las rechaces**: márcalas `needs_review`.

---

## 6. Importación: `php artisan recipes:import`

Opciones: `--dry-run` (por defecto **muestra el diff sin escribir**), `--force` (escribe) y `--only=fxw|mine`.

1. Antes de escribir nada, **copia la BD de Laravel** a `storage/app/backups/database-YYYYmmdd-His.sqlite` y conserva las 10 copias más recientes.
2. Lee `v_recipes` y `posts` de `fxw_source`.
3. Asigna el origen: si `post_url` empieza por `mine:`, `source = mine`; si no, `fxw`.
4. Normaliza la simulación a `FilmSimulation` con un mapa de alias:
   - Porta `SIMS` y `SIM_ALIASES` de `fxw_import.py`.
   - Quita los paréntesis finales (`"Acros (+Y, +R or +G)"` → Acros).
   - Mapea `B&W` → Monochrome y `Standard` → Provia.
   - Compara sin distinguir mayúsculas, puntos ni espacios.
   - Si no hay coincidencia, deja `film_simulation = null`, guarda el texto en `film_simulation_raw` y marca `needs_review = true`.
5. Hace un upsert por (`source_url`, `block_index`):
   - **No toques los campos que estén en `locked_fields`.**
   - `raw_json`, `imported_at` y la relación con los sensores se actualizan siempre.
6. Las recetas de origen `fxw`/`mine` que ya no estén en el origen reciben `missing_from_source_at = now()`, y las que reaparecen lo vuelven a `null`.
7. Al terminar imprime un resumen: creadas, actualizadas, sin cambios, campos protegidos por bloqueo, desaparecidas, `needs_review` y **los valores de simulación sin mapear, agrupados con su recuento**, para poder ampliar el mapa de alias.
8. Si algo falla, el comando sale con código ≠ 0 y un mensaje claro. Nada de errores silenciosos.

### Bloqueo de campos

- Al guardar desde el formulario de edición de Filament, los campos de ajustes que hayan cambiado (`getDirty()`) se añaden a `locked_fields`.
- El import guarda sin pasar por esa lógica.
- En la ficha de la receta aparece un candado junto a cada campo bloqueado, y una acción **"Restaurar valores de origen"** quita el bloqueo y reimporta solo esa receta desde `raw_json`/origen.

---

## 7. Similitud: `App\Services\RecipeSimilarity`

Porta **1:1** `distance()`, `features()`, `wb_mode_penalty()`, `dr_value()` y `level()` de `fxw_mine.py`. Si este documento y el código Python discrepan, manda el Python. La distancia se calcula en memoria sobre todas las recetas (~500); no hace falta precomputarla.

Resumen del algoritmo (menor = más parecido; 0 = idénticas):

- **Simulación:** 0 si es la misma, 2 si es de la misma familia y 6 si no.
- **Modo de WB:**
  - 0,5 si falta en alguna de las dos;
  - si ambas tienen Kelvin, `min(|ΔK| / 500, 3)`;
  - 0 si el modo es el mismo;
  - 0,5 si ambos empiezan por "auto";
  - 1,5 en cualquier otro caso (incluido Kelvin frente a modo sin Kelvin).
- **Ajustes numéricos u ordinales:** peso × |diferencia|. Off=0, Weak=1, Strong=2; Small=0, Large=1; DR100=0, DR200=1, DR400=2, y DR-Auto o cualquier otro valor no vacío = 1.

  | Ajuste | Peso | Ajuste | Peso |
  |---|---|---|---|
  | highlight | 1.0 | wb_shift_red | 0.5 |
  | shadow | 1.0 | wb_shift_blue | 0.5 |
  | color | 1.0 | dynamic range | 0.75 |
  | clarity | 0.4 | grain_roughness | 0.3 |
  | sharpness | 0.2 | grain_size | 0.2 |
  | high_iso_nr | 0.1 | color_chrome_effect | 0.5 |
  | | | color_chrome_fx_blue | 0.5 |

- **Campos ausentes:** si el campo falta en las dos recetas no suma nada; si falta solo en una, suma **peso × 0,5**.
- **Peculiaridad del grano:** `grain_size` solo cuenta si la rugosidad es Weak o Strong. Con grano Off o sin dato, el tamaño se trata como ausente.

API del servicio: `distance(Recipe $a, Recipe $b): SimilarityResult` (total + desglose por campo) y `nearest(Recipe $r, int $n = 10, ?string $sensor = null, bool $sameSimulation = false): Collection`. Los pesos van en `config/fuji.php`, para poder ajustarlos sin tocar código.

### Tests dorados (obligatorios)

Fixtures (créalos con factory en `tests/Fixtures/`). En el WB, `R/B` es el shift de rojo y de azul, y `—` indica que el campo no existe.

| ID | Simulación | DR | Grano | CCE | FX Blue | WB | R/B | H | S | Col | Sharp | NR | Clar |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| A | Reala Ace | DR200 | Weak, Small | Strong | Off | Auto | +1/−2 | −1 | −1 | +4 | −1 | −4 | −2 |
| B | Classic Chrome | DR400 | Strong, Small | Strong | Weak | Daylight | +4/−5 | 0 | +0.5 | +3 | −2 | −4 | −2 |
| C | Reala Ace | DR400 | Off | Weak | Weak | Auto | 0/0 | 0 | 0 | +2 | 0 | −4 | 0 |
| D | PRO Neg. Hi | DR200 | — | — | — | Auto | +1/−2 | −1 | −1 | +2 | — | −2 | — |
| E | Reala Ace | DR200 | Weak, Small | Strong | Weak | Auto | +2/−3 | −1 | −0.5 | +3 | −1 | −4 | −3 |
| F | Classic Negative | DR400 | Strong, Large | Strong | Strong | Auto | +4/−5 | −2 | +1 | +4 | 0 | −4 | −4 |
| G | Classic Negative | DR400 | Strong, Small | Off | Off | Daylight | +4/−5 | −2 | +1 | +3 | −2 | −4 | −2 |
| H | Classic Negative | DR400 | Strong, Small | Strong | Weak | Auto | +3/−5 | −2 | 0 | +4 | 0 | −4 | −2 |
| I | Eterna | DR400 | — | — | — | 5200K | +2/−4 | +0.5 | 0 | −2 | — | — | — |
| J | Eterna Bleach Bypass | DR200 | — | — | — | 6300K | 0/−2 | +1 | +1 | −1 | — | — | — |

Distancias esperadas, calculadas con la implementación Python. Usa un dataset de Pest con tolerancia 0,001:

| Par | Distancia |
|---|---|
| E–A | 3.40 |
| E–B | 13.15 |
| E–C | 8.05 |
| E–D | 5.75 |
| H–F | 3.00 |
| G–F | 5.90 |
| H–G | 5.90 |
| G–G | 0.00 |
| I–J | 9.45 |
| F–I | 19.60 |

Además: la distancia es simétrica, `nearest()` no devuelve la propia receta y E–C desglosa `clarity = 1.2` y `grain_size = 0.1` (que comprueba la peculiaridad del grano). **Si un test dorado falla, el error está en el código PHP, no en la tabla.**

---

## 8. Interfaz (Filament)

### Listado de recetas

- **Columnas:** miniatura (`image_url`), nombre, simulación (badge por familia), DR, H/S/Col compactos, WB resumido (`Auto +1R −2B`), grano, sensores (badges), origen, favorito y valoración.
- **Filtros:**
  - simulación (múltiple), familia, DR, sensor, origen;
  - CCE, FX Blue, grano y modo de WB;
  - **rangos min/max** de Highlight, Shadow y Color;
  - etiquetas, favorito, `needs_review`, con campos bloqueados y desaparecidas del origen.
- **Búsqueda** por nombre, autor y notas.
- **Acciones masivas:** comparar (de 2 a 4 recetas), añadir a una colección y etiquetar.

### Ficha de receta

- Ajustes en el **orden del menú de la cámara**: Film Simulation, Grain Effect, Color Chrome Effect, Color Chrome FX Blue, White Balance, Dynamic Range, D Range Priority, Highlight, Shadow, Color, Sharpness, High ISO NR, Clarity, ISO y Exposure Compensation.
- **"Recetas similares"**: las 10 más cercanas, con la distancia, los campos que difieren y los 3 que más pesan, y filtros por sensor y por "misma simulación".
- **Acciones:**
  - abrir el post original;
  - **duplicar como receta propia** (`source = manual`), para crear variantes;
  - **copiar como texto** en el formato `Clave: valor` (el mismo que entiende `fxw_import.py`);
  - restaurar los valores de origen.

### Comparador

Muestra de 2 a 4 recetas en columnas, con las diferencias resaltadas y la distancia de cada par.

### Formulario de creación y edición

Usa selects para los enums, campos numéricos con el paso y el rango de la sección 5 y validación en el propio formulario. Al guardar una receta de origen `fxw` o `mine`, bloquea los campos que hayan cambiado (sección 6).

### Otros recursos

Etiquetas, colecciones (con reordenación de las posiciones 1–7) y sensores (solo lectura).

**Dashboard opcional:** número de recetas por simulación y por sensor, y recetas pendientes de revisión.

---

## 9. Cómo añadir campos más adelante

- **Ajuste nuevo de la cámara** (por ejemplo, uno que llegue con un firmware):
  1. migración;
  2. caso o enum si aplica;
  3. cast en el modelo;
  4. campo del formulario y columna o filtro de Filament;
  5. mapeo en el import desde `raw_json`;
  6. si debe pesar en la similitud: una entrada en `config/fuji.php`, más un test.

  Documéntalo como checklist en `web/README.md`.
- **Metadato personal:** si es algo con valores libres y repetibles, mejor una etiqueta que una columna nueva.

---

## 10. API JSON (fase opcional)

Rutas `/api` solo en 127.0.0.1, sin autenticación:
- `GET /api/recipes` con los mismos filtros que la UI;
- `GET /api/recipes/{recipe}`;
- `GET /api/recipes/{recipe}/similar?n=&sensor=&same_sim=`;
- `POST /api/recipes`: crea una receta propia. Valida con un Form Request y devuelve 201.

Usa API Resources para la salida y `$request->validated()`, nunca `all()`. El objetivo es que más adelante `fxw_import.py` pueda enviar recetas directamente a la app en lugar de escribir en `fujixweekly.db`.

---

## 11. Tests (Pest)

- **`RecipeSimilarityTest`:** los tests dorados y las propiedades de la sección 7.
- **`FilmSimulationNormalizerTest`:** una tabla de alias a enum, incluidos los casos con paréntesis, los puntos y mayúsculas, y un valor desconocido que debe dar `null`.
- **`ImportRecipesCommandTest`.** Construye una BD de origen temporal con el esquema de la sección 1 y comprueba:
  - que `--dry-run` no escribe nada;
  - la idempotencia: dos imports seguidos generan 0 cambios;
  - que se respetan los `locked_fields`;
  - las desapariciones y reapariciones;
  - los valores sin mapear y `needs_review`;
  - **que el hash del fichero de origen no cambia**;
  - que se crea la copia de seguridad.
- **Filament:** smoke tests de que el listado, la ficha y el formulario renderizan, y de que un filtro de rango filtra.
- Cada fase termina con `./vendor/bin/pint` y `php artisan test` en verde.

---

## 12. Fases y criterios de aceptación

| Fase | Contenido | Hecho cuando… |
|---|---|---|
| 0 | `git init` en `~/fuji-recipes` (con `.gitignore` para `.venv/`, `.fxw_cache/`, `probe_*/`, `*.db`, `web/vendor`, `web/node_modules`, `web/.env`, `web/database/*.sqlite`); crear `web/`; configurar `.env`, Pest y Pint | `php artisan test` en verde; la app arranca en 127.0.0.1 |
| 1 | Enums, migraciones, modelos, factories y seeders de sensores | Las migraciones van de cero hasta el final y también hacen rollback; las factories generan recetas válidas |
| 2 | Comando `recipes:import` con dry-run, backup y bloqueos | Tests en verde. Un dry-run real sobre `fujixweekly.db` muestra el resumen y **el usuario lo revisa antes del primer `--force`** |
| 3 | `RecipeSimilarity` + configuración de pesos | Los 10 tests dorados en verde |
| 4 | Filament: listado, filtros, ficha, similares y formulario | Se pueden filtrar las recetas X-Trans V de Classic Negative con Highlight ≤ −1 y ver sus similares |
| 5 | Comparador, duplicar, copiar como texto, etiquetas y colecciones | Se pueden comparar 3 recetas y crear una colección de 7 |
| 6 | (Opcional) API JSON | Feature tests de la API en verde |

Haz un commit por fase, con un mensaje descriptivo.

---

## 13. Reglas de trabajo

- **Primero lectura, luego una prueba pequeña y por último la escritura masiva.** Cualquier operación que escriba datos reales tiene un modo de simulación y se valida antes de ejecutarla de verdad.
- **Advertencias antes de los comandos.** Cuando propongas un comando con efectos (migraciones con `--force`, imports o borrados), explica primero qué hace y qué riesgo tiene, y después el comando.
- **Nunca** escribas en `fujixweekly.db` ni modifiques los scripts `fxw_*.py`. Si algo de ahí parece mal, avisa en lugar de tocarlo.
- Nada de errores silenciosos ni `try/catch` que se traguen excepciones.
- Si para un cambio pequeño basta con tocar unas líneas, indica exactamente cuáles en lugar de reescribir el fichero entero.
- No añadas paquetes ni cambies la versión de Laravel sin preguntar.
- Al terminar cada fase, resume qué se hizo, qué queda pendiente y cualquier decisión que necesite confirmación.

---

## 14. Fuera de alcance (por ahora)

- **Transferir recetas a la cámara por USB/PTP** (slots C1–C7). Está en investigación aparte, con sondeos de solo lectura. Las colecciones de la sección 5 están pensadas para conectarse con eso más adelante.
- Ejecutar el scraper o el OCR desde Laravel.
- Multiusuario, despliegue o cualquier cosa que no sea 127.0.0.1.
