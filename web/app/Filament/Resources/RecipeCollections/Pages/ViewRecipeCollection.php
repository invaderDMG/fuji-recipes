<?php

namespace App\Filament\Resources\RecipeCollections\Pages;

use App\Filament\Resources\RecipeCollections\RecipeCollectionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecipeCollection extends ViewRecord
{
    protected static string $resource = RecipeCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
