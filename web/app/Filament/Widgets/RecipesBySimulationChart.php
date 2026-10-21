<?php

namespace App\Filament\Widgets;

use App\Enums\FilmSimulation;
use App\Models\Recipe;
use Filament\Widgets\ChartWidget;

class RecipesBySimulationChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Recetas por simulación';

    protected function getData(): array
    {
        $counts = Recipe::query()
            ->selectRaw('film_simulation, count(*) as total')
            ->groupBy('film_simulation')
            ->orderByDesc('total')
            ->pluck('total', 'film_simulation');

        $sims = $counts->keys()->map(fn (?string $sim) => $sim === null || $sim === '' ? null : FilmSimulation::from($sim));
        // Cada barra con la tinta de su familia, como en el listado
        $inks = $sims->map(fn (?FilmSimulation $sim) => FilmSimulation::FAMILY_INK[$sim?->family() ?? 'mono'])->all();

        return [
            'datasets' => [[
                'label' => 'Recetas',
                'data' => $counts->values()->all(),
                'backgroundColor' => $inks,
                'borderColor' => $inks,
            ]],
            'labels' => $sims->map(fn (?FilmSimulation $sim) => $sim?->label() ?? 'Sin simulación')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
