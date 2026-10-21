<?php

namespace App\Import;

use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\GrainSize;
use BackedEnum;

/**
 * Normaliza los ajustes de texto libre del origen. Cada método devuelve el
 * valor normalizado y si el texto original necesita revisión manual.
 *
 * Los niveles se interpretan por la primera palabra, igual que level() en
 * fxw_mine.py, para que la similitud dé los mismos resultados.
 */
final class SettingsNormalizer
{
    public const REVIEW_UNMAPPED = 'sin mapear';

    public const REVIEW_AMBIGUOUS = 'ambiguo';

    /**
     * @template T of EffectLevel|GrainSize
     *
     * @param  class-string<T>  $enum
     * @return array{0: T|null, 1: string|null}
     */
    public static function level(?string $raw, string $enum): array
    {
        $raw = self::clean($raw);

        if ($raw === null) {
            return [null, null];
        }

        // "Off/NA", "Off or N/A", "N/A (X-Trans III) or Off (X-T3/X-T30)": el ajuste no
        // existe en cámaras antiguas y va en Off en las que lo tienen
        if ($enum === EffectLevel::class && preg_match('#\bn/?a\b#i', $raw) && preg_match('/\boff\b/i', $raw)) {
            return [EffectLevel::Off, null];
        }

        $first = strtok(mb_strtolower($raw), " \t");
        $value = self::caseFor($enum, $first);

        if ($value === null) {
            return [null, self::REVIEW_UNMAPPED];
        }

        return [$value, strcasecmp($raw, $value->value) === 0 ? null : self::REVIEW_AMBIGUOUS];
    }

    /**
     * Valores por sensor cuando el texto los distingue, p. ej.
     * "Strong (X-Trans IV), Weak (X-Trans V)" o "Strong - Weak for X-Trans V".
     *
     * @template T of EffectLevel|GrainSize
     *
     * @param  class-string<T>  $enum
     * @return array<string, T> slug del sensor => valor
     */
    public static function variants(?string $raw, string $enum): array
    {
        $values = implode('|', array_map(fn (BackedEnum $case) => $case->value, $enum::cases()));
        $sensor = '(X-Trans\s+[IVX]+)\b';

        preg_match_all("/\b({$values})\s*(?:\(\s*{$sensor}\s*\)|for\s+{$sensor})/i", (string) $raw, $matches, PREG_SET_ORDER);

        $variants = [];
        foreach ($matches as $m) {
            $slug = strtolower(preg_replace('/\s+/', '-', $m[2] !== '' ? $m[2] : $m[3]));
            $variants[$slug] = self::caseFor($enum, strtolower($m[1]));
        }

        return $variants;
    }

    /**
     * @return array{0: DynamicRange|null, 1: string|null, 2: string|null} [rango, prioridad, revisión]
     */
    public static function dynamicRange(?string $raw): array
    {
        $raw = self::clean($raw);

        if ($raw === null) {
            return [null, null, null];
        }

        // Con D-Range Priority activo, el DR lo fija la cámara (dr_value() de Python también da 1).
        if (preg_match('/priority|dr-p/i', $raw)) {
            return [DynamicRange::Auto, self::priority($raw), null];
        }

        if (preg_match('/(\d{3})/', $raw, $m)) {
            $value = DynamicRange::tryFrom('DR'.$m[1]);

            return [$value, null, match (true) {
                $value === null => self::REVIEW_UNMAPPED,
                strcasecmp($raw, $value->value) !== 0 => self::REVIEW_AMBIGUOUS,
                default => null,
            }];
        }

        if (stripos($raw, 'auto') !== false) {
            return [DynamicRange::Auto, null, strcasecmp($raw, DynamicRange::Auto->value) === 0 ? null : self::REVIEW_AMBIGUOUS];
        }

        return [null, null, self::REVIEW_UNMAPPED];
    }

    /**
     * Normaliza D Range Priority a Off, Weak, Strong o Auto.
     */
    public static function priority(?string $raw): ?string
    {
        $raw = self::clean($raw);

        if ($raw === null) {
            return null;
        }

        foreach (['Strong', 'Weak', 'Auto', 'Off'] as $level) {
            if (stripos($raw, $level) !== false) {
                return $level;
            }
        }

        return $raw;
    }

    /**
     * Normaliza el modo de WB (la parte anterior a la primera coma).
     *
     * @return array{0: string|null, 1: string|null}
     */
    public static function whiteBalanceMode(?string $raw): array
    {
        $raw = self::clean($raw);

        if ($raw === null) {
            return [null, null];
        }

        $mode = match (true) {
            (bool) preg_match('/^\d{4,5}\s*K\b/i', $raw) => 'Kelvin',
            (bool) preg_match('/^auto white priority/i', $raw) => 'Auto White Priority',
            (bool) preg_match('/^auto ambience priority/i', $raw) => 'Auto Ambience Priority',
            (bool) preg_match('/^(auto\b|awb$)/i', $raw) => 'Auto',
            (bool) preg_match('/fluorescent\s*1|daylight fluorescent/i', $raw) => 'Fluorescent 1',
            (bool) preg_match('/fluorescent\s*2|warm.white fluorescent/i', $raw) => 'Fluorescent 2',
            (bool) preg_match('/fluorescent\s*3|cool white fluorescent/i', $raw) => 'Fluorescent 3',
            (bool) preg_match('/^daylight\b/i', $raw) => 'Daylight',
            (bool) preg_match('/^(cloudy\/)?shade$/i', $raw) => 'Shade',
            (bool) preg_match('/^incandescent$/i', $raw) => 'Incandescent',
            (bool) preg_match('/^underwater$/i', $raw) => 'Underwater',
            (bool) preg_match('/^custom$/i', $raw) => 'Custom',
            default => null,
        };

        return [$mode, $mode === null ? self::REVIEW_UNMAPPED : null];
    }

    public static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    private static function caseFor(string $enum, string|false $word): ?BackedEnum
    {
        foreach ($enum::cases() as $case) {
            if ($word !== false && strtolower($case->value) === $word) {
                return $case;
            }
        }

        return null;
    }
}
