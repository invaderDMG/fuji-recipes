<?php

namespace Database\Seeders;

use App\Models\Sensor;
use Illuminate\Database\Seeder;

/**
 * Los 9 índices de sensor de fujixweekly.com (los slugs de recipe_sensors).
 * Idempotente: se puede ejecutar varias veces.
 */
class SensorSeeder extends Seeder
{
    public const SENSORS = [
        'x-trans-v' => ['X-Trans V', 'X-H2, X-H2S, X-T5, X-S20, X100VI, X-T50, X-M5, X-E5, X-T30 III'],
        'x-trans-iv' => ['X-Trans IV', 'X-T3, X-T30, X-Pro3, X100V, X-T4, X-S10, X-E4, X-T30 II'],
        'x-trans-iii' => ['X-Trans III', 'X-Pro2, X-T2, X-T20, X-E3, X-H1, X100F'],
        'x-trans-ii' => ['X-Trans II', 'X-T1, X-T10, X-E2, X-E2S, X100T, X70, X30'],
        'x-trans-i' => ['X-Trans I', 'X-Pro1, X-E1, X-M1, X100S, X20'],
        'bayer' => ['Bayer', 'X-A1…X-A7, X-T100, X-T200, XF10'],
        'exr-cmos' => ['EXR-CMOS', 'X10, XF1, X-S1'],
        'gfx' => ['GFX', 'GFX 50S, GFX 50R, GFX 100, GFX 100S, GFX 50S II, GFX 100 II, GFX 100S II'],
        'full-spectrum' => ['Full Spectrum', 'Cámaras X modificadas a espectro completo (infrarrojo)'],
    ];

    public function run(): void
    {
        foreach (self::SENSORS as $slug => [$label, $cameras]) {
            Sensor::updateOrCreate(['slug' => $slug], ['label' => $label, 'cameras' => $cameras]);
        }
    }
}
