<?php

namespace App\Http\Requests;

use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\GrainSize;
use App\Filament\Resources\Recipes\Schemas\RecipeForm;
use App\Models\Recipe;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/recipes: crea una receta propia (source = manual). Solo se
 * aceptan estos campos; el resto (source, locked_fields…) se ignora.
 */
class StoreRecipeRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'source_url' => ['nullable', 'string', 'max:255', $this->notExisting(...)],
            'film_simulation' => ['nullable', Rule::enum(FilmSimulation::class)],
            'film_simulation_raw' => ['nullable', 'string', 'max:255'],
            'dynamic_range' => ['nullable', Rule::enum(DynamicRange::class)],
            'd_range_priority' => ['nullable', Rule::in(RecipeForm::D_RANGE_PRIORITY)],
            'grain_roughness' => ['nullable', Rule::enum(EffectLevel::class)],
            'grain_size' => ['nullable', Rule::enum(GrainSize::class)],
            'color_chrome_effect' => ['nullable', Rule::enum(EffectLevel::class)],
            'color_chrome_fx_blue' => ['nullable', Rule::enum(EffectLevel::class)],
            'smooth_skin' => ['nullable', Rule::enum(EffectLevel::class)],
            'wb_mode' => ['nullable', Rule::in(RecipeForm::WB_MODES)],
            'white_balance_raw' => ['nullable', 'string', 'max:255'],
            'monochromatic_color' => ['nullable', 'string', 'max:255'],
            'tone_curve' => ['nullable', 'string', 'max:255'],
            'iso_raw' => ['nullable', 'string', 'max:255'],
            'iso_max' => ['nullable', 'integer', 'min:80', 'max:51200'],
            'exposure_compensation' => ['nullable', 'string', 'max:255'],
            'raw_json' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'is_favorite' => ['boolean'],
            'sensors' => ['array'],
            'sensors.*' => ['string', 'exists:sensors,slug'],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:255'],
        ];

        foreach (Recipe::RANGES as $field => [$min, $max, $step]) {
            $rules[$field] = ['nullable', 'numeric', "between:{$min},{$max}", "multiple_of:{$step}"];
        }
        $rules['wb_kelvin'][] = Rule::requiredIf(fn () => $this->input('wb_mode') === 'Kelvin');

        return $rules;
    }

    /**
     * Una receta borrada (borrado lógico) no se vuelve a insertar.
     */
    private function notExisting(string $attribute, mixed $value, Closure $fail): void
    {
        $existing = Recipe::withTrashed()->where('source_url', $value)->where('block_index', 0)->first();

        if ($existing !== null) {
            $fail($existing->trashed()
                ? "Esa receta está borrada en la app (#{$existing->id}) y no se vuelve a insertar."
                : "Ya existe una receta con ese source_url (#{$existing->id}).");
        }
    }
}
