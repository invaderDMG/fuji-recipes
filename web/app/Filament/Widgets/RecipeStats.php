<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\MyCameraSettings;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Support\MyCamera;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RecipeStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Recetas', Recipe::count()),
            Stat::make('Compatibles con tu '.MyCamera::label(), Recipe::compatibleWith(MyCamera::sensorSlug())->count())
                ->url(MyCameraSettings::getUrl())
                ->description('Cambiar cámara'),
            Stat::make('Pendientes de revisión', Recipe::where('needs_review', true)->count())
                ->color('warning')
                ->url(RecipeResource::getUrl('index', ['filters' => ['needs_review' => ['value' => true]]])),
            Stat::make('Favoritas', Recipe::where('is_favorite', true)->count()),
        ];
    }
}
