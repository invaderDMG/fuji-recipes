<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Enums\RecipeSource;
use App\Filament\Resources\Recipes\RecipeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecipe extends CreateRecord
{
    protected static string $resource = RecipeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = RecipeSource::Manual;

        return $data;
    }
}
