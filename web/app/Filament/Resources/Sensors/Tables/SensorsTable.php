<?php

namespace App\Filament\Resources\Sensors\Tables;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Sensor;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SensorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('recipes'))
            ->columns([
                TextColumn::make('label')
                    ->label('Sensor')
                    ->weight('medium'),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->fontFamily('mono')
                    ->color('gray'),
                TextColumn::make('cameras')
                    ->label('Cámaras')
                    ->wrap(),
                TextColumn::make('recipes_count')
                    ->label('Recetas')
                    ->alignEnd()
                    ->url(fn (Sensor $record) => RecipeResource::getUrl('index', ['filters' => ['sensors' => ['values' => [$record->id]]]])),
            ])
            ->defaultSort('id')
            ->paginated(false);
    }
}
