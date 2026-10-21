<?php

namespace App\Import;

use App\Enums\EffectLevel;
use App\Enums\GrainSize;
use App\Enums\RecipeSource;
use App\Models\Recipe;

/**
 * Convierte una fila de v_recipes en atributos del modelo Recipe y anota los
 * campos que necesitan revisión manual.
 */
final class SourceRecipeMapper
{
    public const REVIEW_EMPTY = 'vacío';

    public const REVIEW_OUT_OF_RANGE = 'fuera de rango';

    /**
     * @param  array<string, string|null>  $sensors  slug => index_label
     */
    public function map(object $row, array $sensors): MappedRecipe
    {
        $issues = [];
        $clean = SettingsNormalizer::clean(...);

        $simRaw = $clean($row->film_simulation);
        $sim = FilmSimulationNormalizer::normalize($simRaw);
        if ($sim === null) {
            $issues['film_simulation'] = $simRaw === null ? self::REVIEW_EMPTY : SettingsNormalizer::REVIEW_UNMAPPED;
        }

        [$dr, $priorityFromDr, $issues['dynamic_range']] = SettingsNormalizer::dynamicRange($row->dynamic_range);
        [$grainRoughness, $issues['grain_roughness']] = SettingsNormalizer::level($row->grain_roughness, EffectLevel::class);
        [$grainSize, $issues['grain_size']] = SettingsNormalizer::level($row->grain_size, GrainSize::class);
        [$cce, $issues['color_chrome_effect']] = SettingsNormalizer::level($row->color_chrome_effect, EffectLevel::class);
        [$fxBlue, $issues['color_chrome_fx_blue']] = SettingsNormalizer::level($row->color_chrome_fx_blue, EffectLevel::class);
        [$smoothSkin, $issues['smooth_skin']] = SettingsNormalizer::level($row->smooth_skin, EffectLevel::class);
        [$wbMode, $issues['wb_mode']] = SettingsNormalizer::whiteBalanceMode($row->wb_mode);

        $attributes = [
            'name' => $clean($row->name) ?? $clean($row->post_title) ?? $row->post_url,
            'author' => $clean($row->author ?? null),
            'published_at' => $clean($row->published) === null ? null : substr($row->published, 0, 10),
            'image_url' => $clean($row->image_url),
            'film_simulation' => $sim,
            'film_simulation_raw' => $simRaw,
            'dynamic_range' => $dr,
            'd_range_priority' => SettingsNormalizer::priority($row->d_range_priority) ?? $priorityFromDr,
            'grain_roughness' => $grainRoughness,
            'grain_size' => $grainSize,
            'color_chrome_effect' => $cce,
            'color_chrome_fx_blue' => $fxBlue,
            'wb_mode' => $wbMode,
            'wb_kelvin' => $row->wb_kelvin === null ? null : (int) $row->wb_kelvin,
            'wb_shift_red' => $row->wb_shift_red === null ? null : (int) $row->wb_shift_red,
            'wb_shift_blue' => $row->wb_shift_blue === null ? null : (int) $row->wb_shift_blue,
            'white_balance_raw' => $clean($row->white_balance),
            'highlight' => $row->highlight === null ? null : (float) $row->highlight,
            'shadow' => $row->shadow === null ? null : (float) $row->shadow,
            'color' => $row->color === null ? null : (float) $row->color,
            'sharpness' => $row->sharpness === null ? null : (float) $row->sharpness,
            'high_iso_nr' => $row->high_iso_nr === null ? null : (float) $row->high_iso_nr,
            'clarity' => $row->clarity === null ? null : (float) $row->clarity,
            'monochromatic_color' => $clean($row->monochromatic_color),
            'smooth_skin' => $smoothSkin,
            'tone_curve' => $clean($row->tone_curve),
            'iso_raw' => $clean($row->iso),
            'iso_max' => $row->iso_max === null ? null : (int) $row->iso_max,
            'exposure_compensation' => $clean($row->exposure_compensation),
            'raw_json' => $row->raw_json === null ? null : json_decode($row->raw_json, true, flags: JSON_THROW_ON_ERROR),
        ];

        foreach (Recipe::RANGES as $field => [$min, $max, $step]) {
            $value = $attributes[$field];
            if ($value !== null && ($value < $min || $value > $max || fmod($value - $min, $step) != 0)) {
                $issues[$field] = self::REVIEW_OUT_OF_RANGE;
            }
        }

        return new MappedRecipe(
            source: str_starts_with($row->post_url, 'mine:') ? RecipeSource::Mine : RecipeSource::Fxw,
            sourceUrl: $row->post_url,
            blockIndex: (int) $row->block_index,
            attributes: $attributes,
            issues: array_filter($issues),
            sensors: $sensors,
        );
    }
}
