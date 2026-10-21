<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Capture\CaptureResult;
use App\Enums\RecipeSource;
use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Services\RecipeSimilarity;
use App\Services\SimilarityResult;
use App\Support\MyCamera;
use App\Support\RecipeFields;
use BackedEnum;
use Filament\Resources\Pages\CreateRecord;

class CreateRecipe extends CreateRecord
{
    public const CAPTURE_SESSION_KEY = 'recipe_capture';

    /**
     * Datos de la captura que no van en el formulario (solo escalares, para Livewire).
     *
     * @var array{issues: list<string>, lines: list<string>, similar: list<array{name: string, distance: float, url: string}>, extra: array<string, mixed>}|null
     */
    public ?array $capture = null;

    protected static string $resource = RecipeResource::class;

    protected function fillForm(): void
    {
        parent::fillForm();

        $result = session()->pull(self::CAPTURE_SESSION_KEY);

        if ($result instanceof CaptureResult && $result->recognized()) {
            $this->fillFromCapture($result);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = RecipeSource::Manual;

        return [...$data, ...$this->capture['extra'] ?? []];
    }

    /**
     * Rellena el formulario con lo leído; los campos dudosos con valor por
     * sensor toman la propuesta para «Mi cámara».
     */
    private function fillFromCapture(CaptureResult $result): void
    {
        $attributes = $result->attributes;
        foreach ($result->reviewIssues as $field => $issue) {
            if (filled($issue['suggestion'] ?? null)) {
                $attributes[$field] = $issue['suggestion'];
            }
        }

        $state = array_map(fn ($v) => $v instanceof BackedEnum ? $v->value : $v, $attributes);

        $this->form->fill([
            ...$this->form->getRawState(),
            ...collect($state)->except(['film_simulation_raw', 'white_balance_raw', 'raw_json', 'published_at', 'image_url'])->all(),
            'sensors' => array_filter([MyCamera::sensor()?->id]),
        ]);

        $this->capture = [
            'issues' => collect($result->reviewIssues)->map(fn (array $issue, string $field) => RecipeFields::reviewLine($field, $issue))->values()->all(),
            'lines' => $result->lines,
            'similar' => app(RecipeSimilarity::class)
                ->nearest(new Recipe($attributes), 3)
                ->map(fn (SimilarityResult $r) => [
                    'name' => $r->recipe->name,
                    'distance' => round($r->total, 2),
                    'url' => RecipeResource::getUrl('view', ['record' => $r->recipe]),
                ])->all(),
            'extra' => [
                'film_simulation_raw' => $state['film_simulation_raw'] ?? null,
                'white_balance_raw' => $state['white_balance_raw'] ?? null,
                'raw_json' => $state['raw_json'] ?? null,
            ],
        ];
    }
}
