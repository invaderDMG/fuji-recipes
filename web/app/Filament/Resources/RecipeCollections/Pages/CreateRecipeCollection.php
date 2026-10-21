<?php

namespace App\Filament\Resources\RecipeCollections\Pages;

use App\Filament\Resources\RecipeCollections\RecipeCollectionResource;
use App\Models\RecipeCollection;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateRecipeCollection extends CreateRecord
{
    protected static string $resource = RecipeCollectionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $collection = RecipeCollection::create(Arr::except($data, ['slots']));
            $collection->setRecipes($data['slots'] ?? []);

            return $collection;
        });
    }
}
