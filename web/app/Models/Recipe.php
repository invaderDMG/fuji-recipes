<?php

namespace App\Models;

use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\GrainSize;
use App\Enums\RecipeSource;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    // Borrado lógico: las recetas borradas se conservan y la importación no las recrea
    use SoftDeletes;

    /**
     * Campos de ajustes de la cámara. Son los que se pueden bloquear
     * (locked_fields) al editarlos a mano.
     */
    public const SETTINGS = [
        'film_simulation',
        'film_simulation_raw',
        'dynamic_range',
        'd_range_priority',
        'grain_roughness',
        'grain_size',
        'color_chrome_effect',
        'color_chrome_fx_blue',
        'wb_mode',
        'wb_kelvin',
        'wb_shift_red',
        'wb_shift_blue',
        'white_balance_raw',
        'highlight',
        'shadow',
        'color',
        'sharpness',
        'high_iso_nr',
        'clarity',
        'monochromatic_color',
        'smooth_skin',
        'tone_curve',
        'iso_raw',
        'iso_max',
        'exposure_compensation',
    ];

    /**
     * Campos que la importación no sobrescribe si están en locked_fields.
     */
    public const LOCKABLE = ['name', 'author', 'published_at', 'image_url', ...self::SETTINGS];

    /**
     * Rangos válidos de los ajustes numéricos: [mínimo, máximo, paso].
     */
    public const RANGES = [
        'highlight' => [-2, 4, 0.5],
        'shadow' => [-2, 4, 0.5],
        'color' => [-4, 4, 1],
        'sharpness' => [-4, 4, 1],
        'high_iso_nr' => [-4, 4, 1],
        'clarity' => [-5, 5, 1],
        'wb_shift_red' => [-9, 9, 1],
        'wb_shift_blue' => [-9, 9, 1],
        'wb_kelvin' => [2500, 10000, 10],
    ];

    protected $guarded = ['id'];

    protected $attributes = [
        'block_index' => 0,
        'locked_fields' => '[]',
        'needs_review' => false,
        'is_favorite' => false,
    ];

    protected function casts(): array
    {
        return [
            'source' => RecipeSource::class,
            'published_at' => 'date',
            'film_simulation' => FilmSimulation::class,
            'dynamic_range' => DynamicRange::class,
            'grain_roughness' => EffectLevel::class,
            'grain_size' => GrainSize::class,
            'color_chrome_effect' => EffectLevel::class,
            'color_chrome_fx_blue' => EffectLevel::class,
            'smooth_skin' => EffectLevel::class,
            'wb_kelvin' => 'integer',
            'wb_shift_red' => 'integer',
            'wb_shift_blue' => 'integer',
            'highlight' => 'decimal:1',
            'shadow' => 'decimal:1',
            'color' => 'decimal:1',
            'sharpness' => 'decimal:1',
            'high_iso_nr' => 'decimal:1',
            'clarity' => 'decimal:1',
            'iso_max' => 'integer',
            'raw_json' => 'array',
            'locked_fields' => 'array',
            'needs_review' => 'boolean',
            'missing_from_source_at' => 'datetime',
            'imported_at' => 'datetime',
            'rating' => 'integer',
            'is_favorite' => 'boolean',
            'tested_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Recipe $recipe) {
            $recipe->slug ??= static::uniqueSlug($recipe->name);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'receta';
        $slug = $base;

        for ($i = 2; static::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function isLocked(string $field): bool
    {
        return in_array($field, $this->locked_fields ?? [], true);
    }

    /**
     * Añade a locked_fields los campos modificados a mano, para que la
     * importación no los sobrescriba. Solo aplica a recetas importadas.
     */
    public function lockDirtyFields(): void
    {
        if (! $this->source?->isImported()) {
            return;
        }

        $dirty = array_intersect(array_keys($this->getDirty()), self::LOCKABLE);

        if ($dirty !== []) {
            $this->locked_fields = array_values(array_unique([...$this->locked_fields ?? [], ...$dirty]));
        }
    }

    /**
     * Copia la receta como receta propia (source = manual) para crear variantes:
     * mismos ajustes, sensores y etiquetas, sin datos de importación.
     */
    public function duplicateAsManual(): self
    {
        return DB::transaction(function () {
            $copy = $this->replicate(['slug', 'source_url', 'block_index', 'raw_json', 'locked_fields',
                'needs_review', 'missing_from_source_at', 'imported_at', 'tested_at', 'rating', 'is_favorite']);
            $copy->fill([
                'name' => "{$this->name} (copia)",
                'source' => RecipeSource::Manual,
                'notes' => trim(($this->notes ? "{$this->notes}\n\n" : '')."Variante de «{$this->name}» (#{$this->id})."),
            ]);
            $copy->save();
            $copy->sensors()->sync($this->sensors->mapWithKeys(fn (Sensor $s) => [$s->id => ['index_label' => $s->pivot->index_label]]));
            $copy->tags()->sync($this->tags->modelKeys());

            return $copy;
        });
    }

    /**
     * WB resumido, p. ej. "Auto +1R −2B" o "5200K 0R −4B".
     */
    public function whiteBalanceSummary(): ?string
    {
        $mode = $this->wb_mode === 'Kelvin' && $this->wb_kelvin ? "{$this->wb_kelvin}K" : $this->wb_mode;

        if ($mode === null) {
            return null;
        }

        $shift = fn (?int $v, string $c) => $v === null ? null : ($v === 0 ? '0' : sprintf('%+d', $v)).$c;

        return str_replace('-', '−', implode(' ', array_filter([$mode, $shift($this->wb_shift_red, 'R'), $shift($this->wb_shift_blue, 'B')])));
    }

    /**
     * Grano resumido, p. ej. "Weak, Small".
     */
    public function grainSummary(): ?string
    {
        return implode(', ', array_filter([$this->grain_roughness?->label(), $this->grain_size?->label()])) ?: null;
    }

    /**
     * Filtros compartidos por el listado de Filament y la API.
     *
     * @param  list<string>  $families
     */
    #[Scope]
    protected function inFamilies(Builder $query, array $families): void
    {
        $query->whereIn('film_simulation', collect($families)
            ->flatMap(fn (string $family) => FilmSimulation::ofFamily($family))
            ->map(fn (FilmSimulation $sim) => $sim->value));
    }

    #[Scope]
    protected function inRange(Builder $query, string $field, float|int|string|null $min, float|int|string|null $max): void
    {
        $query
            ->when(filled($min), fn (Builder $q) => $q->where($field, '>=', (float) $min))
            ->when(filled($max), fn (Builder $q) => $q->where($field, '<=', (float) $max));
    }

    #[Scope]
    protected function withLockedFields(Builder $query, bool $locked = true): void
    {
        $locked
            ? $query->whereNotNull('locked_fields')->where('locked_fields', '!=', '[]')
            : $query->where(fn (Builder $q) => $q->whereNull('locked_fields')->orWhere('locked_fields', '[]'));
    }

    /**
     * @param  list<string>  $slugs
     */
    #[Scope]
    protected function forSensors(Builder $query, array $slugs): void
    {
        $query->whereHas('sensors', fn (Builder $q) => $q->whereIn('slug', $slugs));
    }

    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('author', 'like', "%{$term}%")
            ->orWhere('notes', 'like', "%{$term}%"));
    }

    public function sensors(): BelongsToMany
    {
        return $this->belongsToMany(Sensor::class)->withPivot('index_label');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(RecipeCollection::class, 'collection_recipe', 'recipe_id', 'collection_id')
            ->withPivot('position');
    }
}
