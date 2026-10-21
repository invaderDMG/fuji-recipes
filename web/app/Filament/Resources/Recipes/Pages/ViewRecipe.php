<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Filament\Resources\Recipes\Widgets\SimilarRecipes;
use App\Import\RecipeImporter;
use App\Import\SourceReader;
use App\Models\Recipe;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use RuntimeException;

/**
 * @property Recipe $record
 */
class ViewRecipe extends ViewRecord
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openPost')
                ->label('Abrir post original')
                ->icon(Heroicon::ArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn () => $this->record->source_url, shouldOpenInNewTab: true)
                ->visible(fn () => str_starts_with((string) $this->record->source_url, 'http')),
            EditAction::make(),
            Action::make('restoreSource')
                ->label('Restaurar valores de origen')
                ->icon(Heroicon::ArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Quita todos los bloqueos y vuelve a cargar esta receta desde fujixweekly.db. Se perderán los cambios hechos a mano en sus campos importados.')
                ->visible(fn () => $this->record->source->isImported())
                ->action(function () {
                    try {
                        (new RecipeImporter(SourceReader::fromConfig()))->restore($this->record);
                    } catch (DomainException|RuntimeException $e) {
                        Notification::make()->danger()->title('No se pudo restaurar')->body($e->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title('Valores de origen restaurados')->send();
                    $this->redirect(RecipeResource::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [SimilarRecipes::class];
    }
}
