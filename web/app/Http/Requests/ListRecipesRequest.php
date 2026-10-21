<?php

namespace App\Http\Requests;

use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\RecipeSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros de GET /api/recipes: los mismos que el listado de la interfaz.
 */
class ListRecipesRequest extends FormRequest
{
    public const SORTS = ['name', '-name', 'published_at', '-published_at', 'highlight', '-highlight', 'shadow', '-shadow', 'color', '-color', 'rating', '-rating'];

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'simulation' => ['array'],
            'simulation.*' => [Rule::enum(FilmSimulation::class)],
            'family' => ['array'],
            'family.*' => [Rule::in(array_keys(FilmSimulation::FAMILIES))],
            'dr' => ['array'],
            'dr.*' => [Rule::enum(DynamicRange::class)],
            'sensor' => ['array'],
            'sensor.*' => ['string', 'exists:sensors,slug'],
            'source' => [Rule::enum(RecipeSource::class)],
            'cce' => ['array'],
            'cce.*' => [Rule::enum(EffectLevel::class)],
            'fx_blue' => ['array'],
            'fx_blue.*' => [Rule::enum(EffectLevel::class)],
            'grain' => ['array'],
            'grain.*' => [Rule::enum(EffectLevel::class)],
            'wb_mode' => ['array'],
            'wb_mode.*' => ['string'],
            'highlight_min' => ['nullable', 'numeric'],
            'highlight_max' => ['nullable', 'numeric'],
            'shadow_min' => ['nullable', 'numeric'],
            'shadow_max' => ['nullable', 'numeric'],
            'color_min' => ['nullable', 'numeric'],
            'color_max' => ['nullable', 'numeric'],
            'tag' => ['array'],
            'tag.*' => ['string'],
            'favorite' => ['boolean'],
            'needs_review' => ['boolean'],
            'locked' => ['boolean'],
            'missing' => ['boolean'],
            'compatible' => ['boolean'],
            'sort' => [Rule::in(self::SORTS)],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'page' => ['integer', 'min:1'],
        ];
    }

    /**
     * Admite ?sensor=a,b además de ?sensor[]=a&sensor[]=b, y ?favorite=true/false.
     */
    protected function prepareForValidation(): void
    {
        foreach (['simulation', 'family', 'dr', 'sensor', 'cce', 'fx_blue', 'grain', 'wb_mode', 'tag'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => explode(',', $this->input($key))]);
            }
        }

        $this->merge(QueryBooleans::normalize($this, ['favorite', 'needs_review', 'locked', 'missing', 'compatible']));
    }
}
