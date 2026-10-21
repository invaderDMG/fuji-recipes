<?php

namespace App\Import;

use App\Enums\FilmSimulation;

/**
 * Convierte el texto libre de la simulación en un FilmSimulation.
 *
 * Porta SIMS y SIM_ALIASES de fxw_import.py. Compara sin distinguir mayúsculas,
 * puntos ni espacios, quita los paréntesis finales y, si hay varias opciones
 * separadas por "/", se queda con la primera ("Provia/STD" → Provia).
 */
final class FilmSimulationNormalizer
{
    /**
     * SIM_ALIASES de fxw_import.py más las variantes que aparecen en fujixweekly.db.
     */
    private const ALIASES = [
        'standard' => FilmSimulation::Provia,
        'provia standard' => FilmSimulation::Provia,
        'std' => FilmSimulation::Provia,
        'vivid' => FilmSimulation::Velvia,
        'velvia vivid' => FilmSimulation::Velvia,
        'soft' => FilmSimulation::Astia,
        'astia soft' => FilmSimulation::Astia,
        'classic neg' => FilmSimulation::ClassicNegative,
        'nostalgic negative' => FilmSimulation::NostalgicNeg,
        'pro neg hi' => FilmSimulation::ProNegHi,
        'pro negative hi' => FilmSimulation::ProNegHi,
        'pro neg std' => FilmSimulation::ProNegStd,
        'pro neg standard' => FilmSimulation::ProNegStd,
        'pro negative std' => FilmSimulation::ProNegStd,
        'pro negative standard' => FilmSimulation::ProNegStd,
        'eterna cinema' => FilmSimulation::Eterna,
        'bleach bypass' => FilmSimulation::EternaBleachBypass,
        'acros+y' => FilmSimulation::AcrosYe,
        'monochrome+y' => FilmSimulation::MonochromeYe,
        'b&w' => FilmSimulation::Monochrome,
    ];

    /** @var array<string, FilmSimulation>|null */
    private static ?array $map = null;

    public static function normalize(?string $raw): ?FilmSimulation
    {
        $key = self::key($raw ?? '');

        if ($key === '') {
            return null;
        }

        $map = self::map();

        if (isset($map[$key])) {
            return $map[$key];
        }

        if (str_contains($key, '/')) {
            return $map[strstr($key, '/', true)] ?? null;
        }

        return null;
    }

    public static function key(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s*\(.*\)\s*$/', '', $value);

        return preg_replace('/[.\s]+/', '', $value);
    }

    /**
     * @return array<string, FilmSimulation>
     */
    private static function map(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $map = [];

        foreach (FilmSimulation::cases() as $sim) {
            $map[self::key($sim->value)] = $sim;
            $map[self::key($sim->label())] = $sim;
        }

        foreach (self::ALIASES as $alias => $sim) {
            $map[self::key($alias)] = $sim;
        }

        return self::$map = $map;
    }
}
