<?php

namespace Tests\Fixtures;

use App\Enums\DynamicRange as DR;
use App\Enums\EffectLevel as L;
use App\Enums\FilmSimulation as Sim;
use App\Enums\GrainSize as G;
use App\Models\Recipe;

/**
 * Recetas A–J de los tests dorados (sección 7 del plan). null = el campo no existe.
 */
final class GoldenRecipes
{
    private const COLUMNS = [
        'film_simulation', 'dynamic_range', 'grain_roughness', 'grain_size',
        'color_chrome_effect', 'color_chrome_fx_blue', 'wb_mode', 'wb_kelvin',
        'wb_shift_red', 'wb_shift_blue', 'highlight', 'shadow', 'color',
        'sharpness', 'high_iso_nr', 'clarity',
    ];

    public static function rows(): array
    {
        return [
            'A' => [Sim::RealaAce, DR::DR200, L::Weak, G::Small, L::Strong, L::Off, 'Auto', null, 1, -2, -1, -1, 4, -1, -4, -2],
            'B' => [Sim::ClassicChrome, DR::DR400, L::Strong, G::Small, L::Strong, L::Weak, 'Daylight', null, 4, -5, 0, 0.5, 3, -2, -4, -2],
            'C' => [Sim::RealaAce, DR::DR400, L::Off, null, L::Weak, L::Weak, 'Auto', null, 0, 0, 0, 0, 2, 0, -4, 0],
            'D' => [Sim::ProNegHi, DR::DR200, null, null, null, null, 'Auto', null, 1, -2, -1, -1, 2, null, -2, null],
            'E' => [Sim::RealaAce, DR::DR200, L::Weak, G::Small, L::Strong, L::Weak, 'Auto', null, 2, -3, -1, -0.5, 3, -1, -4, -3],
            'F' => [Sim::ClassicNegative, DR::DR400, L::Strong, G::Large, L::Strong, L::Strong, 'Auto', null, 4, -5, -2, 1, 4, 0, -4, -4],
            'G' => [Sim::ClassicNegative, DR::DR400, L::Strong, G::Small, L::Off, L::Off, 'Daylight', null, 4, -5, -2, 1, 3, -2, -4, -2],
            'H' => [Sim::ClassicNegative, DR::DR400, L::Strong, G::Small, L::Strong, L::Weak, 'Auto', null, 3, -5, -2, 0, 4, 0, -4, -2],
            'I' => [Sim::Eterna, DR::DR400, null, null, null, null, 'Kelvin', 5200, 2, -4, 0.5, 0, -2, null, null, null],
            'J' => [Sim::EternaBleachBypass, DR::DR200, null, null, null, null, 'Kelvin', 6300, 0, -2, 1, 1, -1, null, null, null],
        ];
    }

    public static function attributes(string $id): array
    {
        return ['name' => "Golden {$id}", ...array_combine(self::COLUMNS, self::rows()[$id])];
    }

    public static function make(string $id): Recipe
    {
        return Recipe::factory()->make(self::attributes($id));
    }

    /**
     * @return array<string, Recipe>
     */
    public static function createAll(): array
    {
        return array_map(
            fn (string $id) => Recipe::factory()->create(self::attributes($id)),
            array_combine(array_keys(self::rows()), array_keys(self::rows())),
        );
    }
}
