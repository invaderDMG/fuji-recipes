<?php

namespace App\Filament\Resources\RecipeCollections\Pages;

use App\Filament\Resources\RecipeCollections\RecipeCollectionResource;
use App\Models\RecipeCollection;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditRecipeCollection extends EditRecord
{
    protected static string $resource = RecipeCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['slots'] = $this->record->recipes->pluck('id')->all();

        return $data;
    }

    /**
     * @param  RecipeCollection  $record
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        DB::transaction(function () use ($record, $data) {
            $record->update(Arr::except($data, ['slots']));
            $record->setRecipes($data['slots'] ?? []);
        });

        return $record;
    }
}
