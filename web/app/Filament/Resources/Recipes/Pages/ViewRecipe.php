<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Filament\Resources\Recipes\Widgets\SimilarRecipes;
use App\Import\RecipeImporter;
use App\Import\SourceReader;
use App\Models\Recipe;
use App\Support\MyCamera;
use App\Support\RecipeText;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;
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
            EditAction::make()
                ->hidden(fn () => $this->record->trashed()),
            RestoreAction::make(),
            DeleteAction::make()
                ->modalDescription('La receta se conserva como borrada y la importación no la vuelve a crear.'),
            Action::make('duplicate')
                ->label('Duplicar como receta propia')
                ->icon(Heroicon::DocumentDuplicate)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Crea una receta manual con los mismos ajustes, sensores y etiquetas, para hacer variantes. La original no cambia.')
                ->action(function () {
                    $copy = $this->record->duplicateAsManual();
                    Notification::make()->success()->title("Creada «{$copy->name}»")->send();
                    $this->redirect(RecipeResource::getUrl('edit', ['record' => $copy]));
                }),
            Action::make('copyText')
                ->label('Copiar como texto')
                ->icon(Heroicon::ClipboardDocument)
                ->color('gray')
                ->alpineClickHandler(fn () => sprintf(
                    'window.navigator.clipboard.writeText(%s).then(() => new FilamentNotification().title(%s).success().send())',
                    Js::from(RecipeText::make($this->record)),
                    Js::from('Receta copiada en formato «Clave: valor»'),
                )),
            Action::make('restoreSource')
                ->label('Restaurar valores de origen')
                ->icon(Heroicon::ArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Quita todos los bloqueos y vuelve a cargar esta receta desde fujixweekly.db. Se perderán los cambios hechos a mano en sus campos importados.')
                ->visible(fn () => $this->record->source->isImported() && ! $this->record->trashed())
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

    public function getSubheading(): string
    {
        if ($this->record->trashed()) {
            return 'Borrada el '.$this->record->deleted_at->format('d/m/Y').'. La importación no la vuelve a crear; puedes recuperarla.';
        }

        return $this->record->isCompatibleWith(MyCamera::sensorSlug())
            ? '✓ Compatible con tu '.MyCamera::label()
            : '⚠ No figura para tu '.MyCamera::label().': puede que le falten o le sobren ajustes.';
    }

    protected function getFooterWidgets(): array
    {
        return [SimilarRecipes::class];
    }
}
