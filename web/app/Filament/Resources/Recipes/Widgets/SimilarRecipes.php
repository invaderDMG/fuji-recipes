<?php

namespace App\Filament\Resources\Recipes\Widgets;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Models\Sensor;
use App\Services\RecipeSimilarity;
use App\Services\SimilarityResult;
use App\Support\RecipeFields;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Las 10 recetas más cercanas a la de la ficha, según RecipeSimilarity.
 */
class SimilarRecipes extends TableWidget
{
    public ?Recipe $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recetas similares')
            ->description('Las 10 más cercanas. Distancia menor = más parecida; 0 = idénticas.')
            ->records(fn (array $filters) => $this->similar(
                $filters['sensor']['value'] ?? null,
                (bool) ($filters['same_simulation']['isActive'] ?? false),
            ))
            ->columns([
                TextColumn::make('distance')
                    ->label('Distancia')
                    ->numeric(2)
                    ->alignEnd(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn (array $record) => $record['author'])
                    ->wrap(),
                TextColumn::make('film_simulation')
                    ->label('Simulación')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('top')
                    ->label('Lo que más pesa'),
                TextColumn::make('differs')
                    ->label('Difiere en')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Nada'),
                TextColumn::make('sensors')
                    ->label('Sensores')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('sensor')
                    ->label('Sensor')
                    ->options(fn () => Sensor::orderBy('id')->pluck('label', 'slug'))
                    ->default(config('fuji.default_sensor')),
                Filter::make('same_simulation')
                    ->label('Misma simulación')
                    ->toggle(),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->recordUrl(fn (array $record) => RecipeResource::getUrl('view', ['record' => $record['id']]))
            ->paginated(false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function similar(?string $sensor, bool $sameSimulation): array
    {
        return app(RecipeSimilarity::class)
            ->nearest($this->record, 10, $sensor ?: null, $sameSimulation)
            ->mapWithKeys(fn (SimilarityResult $r) => [$r->recipe->id => [
                'id' => $r->recipe->id,
                'name' => $r->recipe->name,
                'author' => $r->recipe->author,
                'film_simulation' => $r->recipe->film_simulation,
                'distance' => $r->total,
                'top' => collect($r->topContributors())
                    ->map(fn (float $v, string $f) => RecipeFields::short($f).' '.round($v, 2))
                    ->implode(' · '),
                'differs' => array_map(RecipeFields::short(...), $r->differingFields()),
                'sensors' => $r->recipe->sensors->pluck('label')->all(),
            ]])
            ->all();
    }
}
