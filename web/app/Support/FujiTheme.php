<?php

namespace App\Support;

use App\Models\Recipe;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Estilo «mesa de luz»: una tinta por familia de simulación, escalas como las
 * de la cámara y Archivo con su eje de anchura. El CSS está en public/css/fuji.css.
 */
class FujiTheme
{
    public const FONT = 'Archivo';

    public const FONT_URL = 'https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,300..900&display=swap';

    /** Tinta azul (#2f3c91 en el 700): enlaces, foco y botón principal */
    public const PRIMARY = [
        50 => 'oklch(0.97 0.014 272)',
        100 => 'oklch(0.93 0.03 272)',
        200 => 'oklch(0.87 0.055 272)',
        300 => 'oklch(0.78 0.085 272)',
        400 => 'oklch(0.67 0.12 272)',
        500 => 'oklch(0.56 0.14 272)',
        600 => 'oklch(0.46 0.14 272)',
        700 => 'oklch(0.38 0.13 272)',
        800 => 'oklch(0.32 0.11 272)',
        900 => 'oklch(0.27 0.09 272)',
        950 => 'oklch(0.2 0.07 272)',
    ];

    /** Gris con tinte verde (la mesa de luz), con las luminosidades de Zinc */
    public const GRAY = [
        50 => 'oklch(0.985 0.003 160)',
        100 => 'oklch(0.94 0.006 160)',
        200 => 'oklch(0.9 0.008 160)',
        300 => 'oklch(0.85 0.01 160)',
        400 => 'oklch(0.7 0.012 160)',
        500 => 'oklch(0.55 0.014 160)',
        600 => 'oklch(0.45 0.014 160)',
        700 => 'oklch(0.37 0.012 160)',
        800 => 'oklch(0.27 0.01 165)',
        900 => 'oklch(0.21 0.009 165)',
        950 => 'oklch(0.165 0.008 170)',
    ];

    /** Recorrido de cada ajuste en la cámara (X-Trans V) */
    public const SCALE_RANGE = [
        'highlight' => [-2, 4],
        'shadow' => [-2, 4],
        'color' => [-4, 4],
        'sharpness' => [-4, 4],
        'high_iso_nr' => [-4, 4],
        'clarity' => [-5, 5],
    ];

    public static function stylesheet(): Htmlable
    {
        $path = public_path('css/fuji.css');
        $version = is_file($path) ? filemtime($path) : 0;

        return new HtmlString('<link rel="stylesheet" href="'.e(asset('css/fuji.css')."?v={$version}").'">');
    }

    /**
     * Familia para el CSS (clase fuji-fam-*); sin simulación, la de monocromo.
     */
    public static function family(Recipe $recipe): string
    {
        return $recipe->film_simulation?->family() ?? 'mono';
    }

    /**
     * Geometría de un ajuste sobre su escala, en % del ancho.
     *
     * @return array{min: int, max: int, zero: float, pos: float, from: float, width: float}|null
     */
    public static function scale(string $field, string|float|int|null $value): ?array
    {
        if ($value === null || ! isset(self::SCALE_RANGE[$field])) {
            return null;
        }

        [$min, $max] = self::SCALE_RANGE[$field];
        $at = fn (float $v) => (max($min, min($max, $v)) - $min) / ($max - $min) * 100;
        $zero = $at(0);
        $pos = $at((float) $value);

        return [
            'min' => $min,
            'max' => $max,
            'zero' => $zero,
            'pos' => $pos,
            'from' => min($zero, $pos),
            'width' => abs($pos - $zero),
        ];
    }
}
