<?php

namespace App\Services;

use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\GrainSize;
use App\Import\FilmSimulationNormalizer;
use App\Models\Recipe;
use Illuminate\Support\Collection;

/**
 * Port 1:1 de distance(), features(), wb_mode_penalty(), dr_value() y level()
 * de fxw_mine.py. Si discrepan, manda el Python.
 */
final class RecipeSimilarity
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('fuji.similarity');
    }

    public function distance(Recipe $a, Recipe $b): SimilarityResult
    {
        $parts = [
            'film_simulation' => $this->simulationPenalty($a, $b),
            'wb_mode' => $this->wbModePenalty($a, $b),
        ];

        $fa = $this->features($a);
        $fb = $this->features($b);

        foreach ($this->config['weights'] as $field => $weight) {
            $va = $fa[$field];
            $vb = $fb[$field];

            if ($va === null && $vb === null) {
                continue;
            }

            // Campo que una de las dos no tiene (sensores antiguos)
            $parts[$field] = $va === null || $vb === null
                ? $weight * $this->config['missing_factor']
                : $weight * abs($va - $vb);
        }

        return new SimilarityResult(array_sum($parts), $parts, $b);
    }

    /**
     * Las $n recetas más parecidas a $recipe, de más a menos parecida.
     *
     * @return Collection<int, SimilarityResult>
     */
    public function nearest(Recipe $recipe, int $n = 10, ?string $sensor = null, bool $sameSimulation = false): Collection
    {
        return Recipe::query()
            ->whereKeyNot($recipe->getKey())
            ->when($sensor, fn ($q) => $q->whereHas('sensors', fn ($s) => $s->where('slug', $sensor)))
            ->when($sameSimulation, fn ($q) => $recipe->film_simulation === null
                ? $q->whereNull('film_simulation')->where('film_simulation_raw', $recipe->film_simulation_raw)
                : $q->where('film_simulation', $recipe->film_simulation))
            ->orderBy('id')
            ->get()
            ->map(fn (Recipe $candidate) => $this->distance($recipe, $candidate))
            ->sortBy(fn (SimilarityResult $r) => $r->total, SORT_NUMERIC)
            ->take($n)
            ->values();
    }

    /**
     * 0 si es la misma simulación, penalización menor si es de la misma familia.
     */
    private function simulationPenalty(Recipe $a, Recipe $b): float
    {
        $sa = $a->film_simulation;
        $sb = $b->film_simulation;

        // Sin simulación reconocida, Python compara el texto original
        if ($sa === null && $sb === null) {
            return FilmSimulationNormalizer::key($a->film_simulation_raw ?? '') === FilmSimulationNormalizer::key($b->film_simulation_raw ?? '')
                ? 0.0
                : $this->config['simulation']['different'];
        }

        return match (true) {
            $sa === $sb => 0.0,
            $sa !== null && $sb !== null && $sa->family() === $sb->family() => $this->config['simulation']['same_family'],
            default => $this->config['simulation']['different'],
        };
    }

    private function wbModePenalty(Recipe $a, Recipe $b): float
    {
        $wb = $this->config['white_balance'];
        $ma = mb_strtolower($a->wb_mode ?? '');
        $mb = mb_strtolower($b->wb_mode ?? '');

        if ($ma === '' || $mb === '') {
            return $wb['missing'];
        }
        if ($a->wb_kelvin && $b->wb_kelvin) {
            return min(abs($a->wb_kelvin - $b->wb_kelvin) / $wb['kelvin_step'], $wb['kelvin_max']);
        }
        if ($ma === $mb) {
            return 0.0;
        }
        if (str_starts_with($ma, 'auto') && str_starts_with($mb, 'auto')) {
            return $wb['both_auto'];
        }

        return $wb['different'];
    }

    /**
     * @return array<string, float|int|null>
     */
    private function features(Recipe $r): array
    {
        $grainRoughness = $this->level($r->grain_roughness);

        return [
            'highlight' => $this->number($r->highlight),
            'shadow' => $this->number($r->shadow),
            'color' => $this->number($r->color),
            'clarity' => $this->number($r->clarity),
            'sharpness' => $this->number($r->sharpness),
            'high_iso_nr' => $this->number($r->high_iso_nr),
            'wb_shift_red' => $r->wb_shift_red,
            'wb_shift_blue' => $r->wb_shift_blue,
            'dynamic_range' => $this->drValue($r->dynamic_range),
            'grain_roughness' => $grainRoughness,
            // El tamaño solo cuenta con grano Weak o Strong
            'grain_size' => $grainRoughness ? $this->level($r->grain_size) : null,
            'color_chrome_effect' => $this->level($r->color_chrome_effect),
            'color_chrome_fx_blue' => $this->level($r->color_chrome_fx_blue),
        ];
    }

    private function drValue(?DynamicRange $dr): ?int
    {
        return match ($dr) {
            null => null,
            DynamicRange::DR100 => 0,
            DynamicRange::DR400 => 2,
            DynamicRange::DR200, DynamicRange::Auto => 1,
        };
    }

    private function level(EffectLevel|GrainSize|null $value): ?int
    {
        return $value?->ordinal();
    }

    private function number(string|float|int|null $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
