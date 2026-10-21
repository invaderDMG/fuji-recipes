<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Models\Sensor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RecipeStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $sensor = Sensor::where('slug', config('fuji.default_sensor'))->first();

        return [
            Stat::make('Recetas', Recipe::count()),
            Stat::make('Para tu cámara ('.($sensor->label ?? config('fuji.default_sensor')).')', $sensor?->recipes()->count() ?? 0),
            Stat::make('Pendientes de revisión', Recipe::where('needs_review', true)->count())
                ->color('warning')
                ->url(RecipeResource::getUrl('index', ['filters' => ['needs_review' => ['value' => true]]])),
            Stat::make('Favoritas', Recipe::where('is_favorite', true)->count()),
        ];
    }
}
