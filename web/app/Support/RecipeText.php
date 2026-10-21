<?php

namespace App\Support;

use App\Models\Recipe;

/**
 * Exporta una receta como texto "Clave: valor", el formato que entienden
 * fxw_import.py y fxw_mine.py (KEY_MAP de fxw_scraper.py).
 */
final class RecipeText
{
    public static function make(Recipe $recipe): string
    {
        $lines = [
            'Film Simulation' => $recipe->film_simulation?->value ?? $recipe->film_simulation_raw,
            'Grain Effect' => $recipe->grainSummary(),
            'Color Chrome Effect' => $recipe->color_chrome_effect?->value,
            'Color Chrome FX Blue' => $recipe->color_chrome_fx_blue?->value,
            'Smooth Skin Effect' => $recipe->smooth_skin?->value,
            'White Balance' => self::whiteBalance($recipe),
            'Dynamic Range' => $recipe->dynamic_range?->value,
            'D Range Priority' => $recipe->d_range_priority,
            'Tone Curve' => $recipe->tone_curve,
            'Highlight' => self::number($recipe->highlight),
            'Shadow' => self::number($recipe->shadow),
            'Color' => self::number($recipe->color),
            'Monochromatic Color' => $recipe->monochromatic_color,
            'Sharpness' => self::number($recipe->sharpness),
            'High ISO NR' => self::number($recipe->high_iso_nr),
            'Clarity' => self::number($recipe->clarity),
            'ISO' => $recipe->iso_raw,
            'Exposure Compensation' => $recipe->exposure_compensation,
        ];

        return collect($lines)
            ->filter(fn (?string $value) => filled($value))
            ->map(fn (string $value, string $key) => "{$key}: {$value}")
            ->implode("\n");
    }

    /**
     * "Auto, +1 Red & -2 Blue" o "5200K, 0 Red & -4 Blue".
     */
    private static function whiteBalance(Recipe $recipe): ?string
    {
        $mode = $recipe->wb_mode === 'Kelvin' && $recipe->wb_kelvin ? "{$recipe->wb_kelvin}K" : $recipe->wb_mode;

        if ($mode === null) {
            return $recipe->white_balance_raw;
        }

        if ($recipe->wb_shift_red === null && $recipe->wb_shift_blue === null) {
            return $mode;
        }

        return sprintf('%s, %s Red & %s Blue', $mode, self::number($recipe->wb_shift_red ?? 0), self::number($recipe->wb_shift_blue ?? 0));
    }

    /**
     * Con signo ASCII: num() de fxw_scraper.py no reconoce "−".
     */
    private static function number(string|float|int|null $value): ?string
    {
        return $value === null ? null : str_replace('−', '-', RecipeFields::signed($value));
    }
}
