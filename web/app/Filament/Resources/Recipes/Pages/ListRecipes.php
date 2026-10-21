<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Support\MyCamera;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListRecipes extends ListRecords
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Filtro rápido por «Mi cámara».
     */
    public function getTabs(): array
    {
        $sensor = MyCamera::sensorSlug();

        return [
            'compatible' => Tab::make('Compatibles con mi '.MyCamera::camera())
                ->icon(Heroicon::Camera)
                ->badge(fn () => Recipe::compatibleWith($sensor)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->compatibleWith($sensor)),
            'all' => Tab::make('Todas')
                ->badge(fn () => Recipe::count()),
            'review' => Tab::make('Por revisar')
                ->icon(Heroicon::ExclamationTriangle)
                ->badge(fn () => Recipe::where('needs_review', true)->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('needs_review', true)),
            'incompatible' => Tab::make('No compatibles')
                ->badge(fn () => Recipe::compatibleWith($sensor, false)->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->compatibleWith($sensor, false)),
        ];
    }
}
