<?php

namespace App\Models;

use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\GrainSize;
use App\Enums\RecipeSource;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

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

        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function isLocked(string $field): bool
    {
        return in_array($field, $this->locked_fields ?? [], true);
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
