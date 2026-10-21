<?php

namespace App\Filament\Widgets;

use App\Models\Sensor;
use Filament\Widgets\ChartWidget;

class RecipesBySensorChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Recetas por sensor';

    protected function getData(): array
    {
        $sensors = Sensor::withCount('recipes')->orderBy('id')->get();

        return [
            'datasets' => [['label' => 'Recetas', 'data' => $sensors->pluck('recipes_count')->all()]],
            'labels' => $sensors->pluck('label')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
