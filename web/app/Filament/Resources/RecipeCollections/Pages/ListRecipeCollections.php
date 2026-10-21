<?php

namespace App\Filament\Resources\RecipeCollections\Pages;

use App\Filament\Resources\RecipeCollections\RecipeCollectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecipeCollections extends ListRecords
{
    protected static string $resource = RecipeCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
