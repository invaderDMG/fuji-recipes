<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Services\RecipeSimilarity;
use App\Support\MyCamera;
use App\Support\RecipeFields;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Url;

/**
 * Compara de 2 a 4 recetas en columnas, resaltando los ajustes que difieren,
 * con la distancia de cada par. Se abre desde la acción masiva "Comparar".
 */
class CompareRecipes extends Page implements HasTable
{
    use InteractsWithTable;

    public const MIN = 2;

    public const MAX = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $title = 'Comparar recetas';

    protected static ?string $slug = 'compare';

    protected static bool $shouldRegisterNavigation = false;

    /** Ids separados por comas */
    #[Url]
    public string $ids = '';

    private ?Collection $recipes = null;

    public function mount(): void
    {
        $count = $this->recipes()->count();

        if ($count < self::MIN || $count > self::MAX) {
            Notification::make()->danger()->title('Selecciona de '.self::MIN.' a '.self::MAX.' recetas para comparar.')->send();
            $this->redirect(RecipeResource::getUrl('index'));
        }
    }

    public static function urlFor(iterable $ids): string
    {
        return static::getUrl(['ids' => collect($ids)->implode(',')]);
    }

    /**
     * Las recetas en el orden de $ids.
     *
     * @return Collection<int, Recipe>
     */
    public function recipes(): Collection
    {
        if ($this->recipes !== null) {
            return $this->recipes;
        }

        $ids = collect(explode(',', $this->ids))->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $found = Recipe::with('sensors')->findMany($ids)->keyBy('id');

        return $this->recipes = new Collection($ids->map(fn (int $id) => $found->get($id))->filter()->values()->all());
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Distancias')
                ->description('Menor = más parecidas; 0 = idénticas.')
                ->columns(3)
                ->schema($this->distanceEntries()),
            Section::make('Escalas')
                ->description('Cada receta sobre la escala de la cámara, con la tinta de su simulación.')
                ->collapsible()
                ->schema([
                    View::make('filament.compare-scales')
                        ->viewData(fn () => ['recipes' => $this->recipes()]),
                ]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Ajustes')
            ->description('En el orden del menú de la cámara. Resaltados los que difieren.')
            ->records(fn (array $filters) => array_filter(
                $this->rows(),
                fn (array $row) => ! ($filters['only_differences']['isActive'] ?? false) || $row['differs'],
            ))
            ->columns([
                TextColumn::make('label')
                    ->label('Ajuste')
                    ->weight(FontWeight::Medium)
                    ->icon(fn (array $record) => $record['differs'] ? Heroicon::ExclamationCircle : null)
                    ->iconColor('warning'),
                ...$this->recipes()->map(fn (Recipe $recipe) => TextColumn::make("r{$recipe->id}")
                    ->label($recipe->name)
                    ->placeholder('—')
                    ->color(fn (array $record) => $record['differs'] ? 'warning' : null)
                    ->weight(fn (array $record) => $record['differs'] ? FontWeight::Bold : null)
                    ->wrap())->all(),
            ])
            ->filters([
                Filter::make('only_differences')
                    ->label('Solo diferencias')
                    ->toggle(),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->paginated(false);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rows(): array
    {
        $settings = [
            'film_simulation' => fn (Recipe $r) => $r->film_simulation?->label() ?? $r->film_simulation_raw,
            'grain_roughness' => fn (Recipe $r) => $r->grainSummary(),
            'color_chrome_effect' => fn (Recipe $r) => $r->color_chrome_effect?->label(),
            'color_chrome_fx_blue' => fn (Recipe $r) => $r->color_chrome_fx_blue?->label(),
            'wb_mode' => fn (Recipe $r) => $r->whiteBalanceSummary(),
            'dynamic_range' => fn (Recipe $r) => $r->dynamic_range?->label(),
            'd_range_priority' => fn (Recipe $r) => $r->d_range_priority,
            'highlight' => fn (Recipe $r) => RecipeFields::signed($r->highlight),
            'shadow' => fn (Recipe $r) => RecipeFields::signed($r->shadow),
            'color' => fn (Recipe $r) => RecipeFields::signed($r->color),
            'sharpness' => fn (Recipe $r) => RecipeFields::signed($r->sharpness),
            'high_iso_nr' => fn (Recipe $r) => RecipeFields::signed($r->high_iso_nr),
            'clarity' => fn (Recipe $r) => RecipeFields::signed($r->clarity),
            'iso_raw' => fn (Recipe $r) => $r->iso_raw,
            'exposure_compensation' => fn (Recipe $r) => $r->exposure_compensation,
            'sensors' => fn (Recipe $r) => $r->sensors->pluck('label')->implode(', ') ?: null,
            'rating' => fn (Recipe $r) => RecipeFields::rating($r->rating),
            'my_camera' => fn (Recipe $r) => $r->isCompatibleWith(MyCamera::sensorSlug()) ? '✓ Compatible' : 'No figura',
        ];
        $labels = ['grain_roughness' => 'Grain Effect', 'sensors' => 'Sensores', 'rating' => 'Valoración', 'my_camera' => 'Mi '.MyCamera::camera()];

        $rows = [];
        foreach ($settings as $field => $value) {
            $values = $this->recipes()->mapWithKeys(fn (Recipe $r) => ["r{$r->id}" => $value($r)]);
            $rows[$field] = [
                'label' => $labels[$field] ?? RecipeFields::label($field),
                ...$values->all(),
                'differs' => $values->unique()->count() > 1,
            ];
        }

        return $rows;
    }

    /**
     * @return list<TextEntry>
     */
    private function distanceEntries(): array
    {
        $similarity = app(RecipeSimilarity::class);
        $recipes = $this->recipes()->values();
        $entries = [];

        foreach ($recipes as $i => $a) {
            foreach ($recipes->slice($i + 1) as $b) {
                $result = $similarity->distance($a, $b);
                $entries[] = TextEntry::make("distance_{$a->id}_{$b->id}")
                    ->label("{$a->name} ↔ {$b->name}")
                    ->state(number_format($result->total, 2))
                    ->helperText(collect($result->topContributors())
                        ->map(fn (float $v, string $f) => RecipeFields::short($f).' '.round($v, 2))
                        ->implode(' · ') ?: 'Idénticas')
                    ->size('lg')
                    ->weight(FontWeight::Bold);
            }
        }

        return $entries;
    }
}
