<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Capture\CaptureReader;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Support\MyCamera;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class ListRecipes extends ListRecords
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fromCapture')
                ->label('Desde captura')
                ->icon(Heroicon::Photo)
                ->color('gray')
                ->modalHeading('Nueva receta desde una captura')
                ->modalDescription('Sube una captura con la receta escrita (o pega el texto). Se abrirá el formulario relleno para que lo revises antes de guardar. La captura no se guarda.')
                ->modalSubmitActionLabel('Leer receta')
                ->schema([
                    FileUpload::make('image')
                        ->label('Captura')
                        ->image()
                        ->acceptedFileTypes(['image/*'])
                        ->maxSize(51200)
                        ->storeFiles(false)
                        ->requiredWithout('text'),
                    Textarea::make('text')
                        ->label('…o pega el texto')
                        ->rows(6)
                        ->placeholder("Film Simulation: Classic Negative\nGrain Effect: Weak, Small\nWhite Balance: Auto, +2 Red & -4 Blue\nHighlight: -1\n…"),
                ])
                ->action(function (array $data, Action $action) {
                    $reader = app(CaptureReader::class);

                    try {
                        $result = filled($data['image'] ?? null)
                            ? $reader->fromImage(collect(Arr::wrap($data['image']))->first()->getRealPath())
                            : $reader->fromText($data['text']);
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title('No se pudo leer')->body($e->getMessage())->send();
                        $action->halt();
                    }

                    if (! $result->recognized()) {
                        Notification::make()->warning()->title('No encuentro una receta')
                            ->body('Necesito al menos la simulación y otros 3 ajustes. Texto leído: '.Str::limit(implode(' · ', $result->lines), 300))
                            ->persistent()->send();
                        $action->halt();
                    }

                    session()->put(CreateRecipe::CAPTURE_SESSION_KEY, $result);
                    $this->redirect(RecipeResource::getUrl('create', ['capture' => 1]));
                }),
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
