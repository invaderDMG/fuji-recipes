<?php

namespace App\Http\Resources;

use App\Filament\Resources\Recipes\RecipeResource as RecipeUi;
use App\Models\Recipe;
use App\Support\RecipeText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recipe
 */
class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $number = fn ($v) => $v === null ? null : (float) $v;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'source' => $this->source,
            'source_url' => $this->source_url,
            'block_index' => $this->block_index,
            'author' => $this->author,
            'published_at' => $this->published_at?->toDateString(),
            'image_url' => $this->image_url,
            'settings' => [
                'film_simulation' => $this->film_simulation,
                'film_simulation_family' => $this->film_simulation?->family(),
                'film_simulation_raw' => $this->film_simulation_raw,
                'grain_roughness' => $this->grain_roughness,
                'grain_size' => $this->grain_size,
                'color_chrome_effect' => $this->color_chrome_effect,
                'color_chrome_fx_blue' => $this->color_chrome_fx_blue,
                'smooth_skin' => $this->smooth_skin,
                'white_balance' => [
                    'mode' => $this->wb_mode,
                    'kelvin' => $this->wb_kelvin,
                    'shift_red' => $this->wb_shift_red,
                    'shift_blue' => $this->wb_shift_blue,
                    'summary' => $this->whiteBalanceSummary(),
                    'raw' => $this->white_balance_raw,
                ],
                'dynamic_range' => $this->dynamic_range,
                'd_range_priority' => $this->d_range_priority,
                'tone_curve' => $this->tone_curve,
                'highlight' => $number($this->highlight),
                'shadow' => $number($this->shadow),
                'color' => $number($this->color),
                'monochromatic_color' => $this->monochromatic_color,
                'sharpness' => $number($this->sharpness),
                'high_iso_nr' => $number($this->high_iso_nr),
                'clarity' => $number($this->clarity),
                'iso' => $this->iso_raw,
                'iso_max' => $this->iso_max,
                'exposure_compensation' => $this->exposure_compensation,
            ],
            'sensors' => $this->whenLoaded('sensors', fn () => $this->sensors->pluck('slug')),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'personal' => [
                'notes' => $this->notes,
                'rating' => $this->rating,
                'is_favorite' => $this->is_favorite,
                'tested_at' => $this->tested_at?->toIso8601String(),
            ],
            'import' => [
                'needs_review' => $this->needs_review,
                'locked_fields' => $this->locked_fields ?? [],
                'missing_from_source_at' => $this->missing_from_source_at?->toIso8601String(),
                'imported_at' => $this->imported_at?->toIso8601String(),
            ],
            'as_text' => RecipeText::make($this->resource),
            'links' => [
                'self' => route('api.recipes.show', $this->resource),
                'similar' => route('api.recipes.similar', $this->resource),
                'ui' => RecipeUi::getUrl('view', ['record' => $this->resource]),
            ],
        ];
    }
}
