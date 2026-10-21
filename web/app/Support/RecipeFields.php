<?php

namespace App\Support;

use App\Models\Recipe;

/**
 * Etiquetas de los campos de una receta, con los nombres del menú de la cámara
 * y en su orden.
 */
final class RecipeFields
{
    public const LABELS = [
        'film_simulation' => 'Film Simulation',
        'film_simulation_raw' => 'Film Simulation (original)',
        'grain_roughness' => 'Grain Effect',
        'grain_size' => 'Grain Size',
        'color_chrome_effect' => 'Color Chrome Effect',
        'color_chrome_fx_blue' => 'Color Chrome FX Blue',
        'smooth_skin' => 'Smooth Skin Effect',
        'wb_mode' => 'White Balance',
        'wb_kelvin' => 'Kelvin',
        'wb_shift_red' => 'WB Shift R',
        'wb_shift_blue' => 'WB Shift B',
        'white_balance_raw' => 'White Balance (original)',
        'dynamic_range' => 'Dynamic Range',
        'd_range_priority' => 'D Range Priority',
        'tone_curve' => 'Tone Curve',
        'highlight' => 'Highlight',
        'shadow' => 'Shadow',
        'color' => 'Color',
        'monochromatic_color' => 'Monochromatic Color',
        'sharpness' => 'Sharpness',
        'high_iso_nr' => 'High ISO NR',
        'clarity' => 'Clarity',
        'iso_raw' => 'ISO',
        'iso_max' => 'ISO máx.',
        'exposure_compensation' => 'Exposure Compensation',
        'name' => 'Nombre',
        'author' => 'Autor',
        'published_at' => 'Publicada',
        'image_url' => 'Imagen',
    ];

    /**
     * Abreviaturas para los desgloses de similitud.
     */
    public const SHORT = [
        'film_simulation' => 'Sim',
        'wb_mode' => 'WB',
        'wb_shift_red' => 'R',
        'wb_shift_blue' => 'B',
        'dynamic_range' => 'DR',
        'grain_roughness' => 'Grano',
        'grain_size' => 'Tamaño grano',
        'color_chrome_effect' => 'CCE',
        'color_chrome_fx_blue' => 'FX Blue',
        'highlight' => 'H',
        'shadow' => 'S',
        'color' => 'Col',
        'sharpness' => 'Sharp',
        'high_iso_nr' => 'NR',
        'clarity' => 'Clar',
    ];

    /**
     * "7/10"; null si no está valorada (0 sí es una nota).
     */
    public static function rating(?int $rating): ?string
    {
        return $rating === null ? null : "{$rating}/".Recipe::RATING_MAX;
    }

    /**
     * @return array<int, string> 0 => "0", …, 10 => "10"
     */
    public static function ratingOptions(): array
    {
        $values = range(0, Recipe::RATING_MAX);

        return array_combine($values, array_map(strval(...), $values));
    }

    public static function display(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            $value instanceof \BackedEnum => (string) $value->value,
            is_numeric($value) => (string) self::signed($value),
            default => (string) $value,
        };
    }

    /**
     * "FX Blue: «Strong (X-Trans IV), Weak (X-Trans V)» → propuesta Weak"
     *
     * @param  array{reason: string, raw: ?string, suggestion: ?string}  $issue
     */
    public static function reviewLine(string $field, array $issue): string
    {
        return self::short($field).' ('.$issue['reason'].'): «'.($issue['raw'] ?? '—').'»'
            .(filled($issue['suggestion'] ?? null) ? ' → propuesta '.$issue['suggestion'] : '');
    }

    public static function label(string $field): string
    {
        return self::LABELS[$field] ?? $field;
    }

    public static function short(string $field): string
    {
        return self::SHORT[$field] ?? self::label($field);
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, string>
     */
    public static function options(array $fields): array
    {
        return array_combine($fields, array_map(self::label(...), $fields));
    }

    /**
     * Número con signo y sin decimales superfluos: +1, −0.5, 0.
     */
    public static function signed(string|float|int|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (float) $value;
        $text = rtrim(rtrim(number_format(abs($value), 1, '.', ''), '0'), '.');

        return match (true) {
            $value > 0 => "+{$text}",
            $value < 0 => "−{$text}",
            default => '0',
        };
    }
}
