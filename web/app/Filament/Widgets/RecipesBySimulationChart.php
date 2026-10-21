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

        return [
            'datasets' => [['label' => 'Recetas', 'data' => $counts->values()->all()]],
            'labels' => $counts->keys()->map(fn (?string $sim) => $sim === null || $sim === ''
                ? 'Sin simulación'
                : FilmSimulation::from($sim)->label())->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
