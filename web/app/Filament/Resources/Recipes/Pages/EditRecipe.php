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
        $dirty = array_keys($record->getDirty());
        $record->lockDirtyFields();

        if (in_array('needs_review', $dirty, true) && ! $record->needs_review) {
            // Desmarcado a mano: los valores actuales se dan por buenos
            $record->markReviewed();

            return $record;
        }

        // Los campos pendientes que se han corregido a mano dejan de estarlo
        if ($record->review_issues) {
            $record->resolveReviewIssues($dirty);
        }
        $record->save();

        return $record;
    }
}
