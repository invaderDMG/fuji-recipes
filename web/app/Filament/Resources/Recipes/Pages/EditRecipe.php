<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRecipe extends EditRecord
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->modalDescription('La receta se conserva como borrada y la importación no la vuelve a crear.'),
            RestoreAction::make(),
        ];
    }

    /**
     * Bloquea los campos cambiados a mano para que la importación no los pise.
     *
     * @param  Recipe  $record
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->fill($data);
        $record->lockDirtyFields();
        $record->save();

        return $record;
    }
}
