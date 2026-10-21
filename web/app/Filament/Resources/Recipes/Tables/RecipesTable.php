<?php

namespace App\Filament\Resources\Recipes\Tables;

use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\RecipeSource;
use App\Filament\Pages\CompareRecipes;
use App\Models\Recipe;
use App\Models\RecipeCollection;
use App\Models\Tag;
use App\Support\RecipeFields;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Component;

class RecipesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('')
                    ->square()
                    ->imageSize(48),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(['name', 'author', 'notes'])
                    ->sortable()
                    ->description(fn (Recipe $record) => $record->author)
                    ->wrap(),
                TextColumn::make('film_simulation')
                    ->label('Simulación')
                    ->badge()
                    ->placeholder($placeholder = '—')
                    ->sortable(),
                TextColumn::make('dynamic_range')
                    ->label('DR')
                    ->placeholder($placeholder),
                self::number('highlight', 'H'),
                self::number('shadow', 'S'),
                self::number('color', 'Col'),
                TextColumn::make('white_balance')
                    ->label('WB')
                    ->state(fn (Recipe $record) => $record->whiteBalanceSummary())
                    ->placeholder($placeholder),
                TextColumn::make('grain')
                    ->label('Grano')
                    ->state(fn (Recipe $record) => $record->grainSummary())
                    ->placeholder($placeholder),
                TextColumn::make('sensors.label')
                    ->label('Sensores')
                    ->badge()
                    ->color('gray')
                    ->limitList(3),
                TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->toggleable(),
                IconColumn::make('is_favorite')
                    ->label('Fav.')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable(),
                TextColumn::make('rating')
                    ->label('Valoración')
                    ->formatStateUsing(fn (?int $state) => $state ? str_repeat('★', $state) : null)
                    ->placeholder($placeholder)
                    ->sortable(),
                IconColumn::make('needs_review')
                    ->label('Revisar')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('published_at')
                    ->label('Publicada')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('film_simulation')
                    ->label('Simulación')
                    ->options(FilmSimulation::class)
                    ->multiple(),
                SelectFilter::make('family')
                    ->label('Familia')
                    ->options(FilmSimulation::FAMILIES)
                    ->multiple()
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['values'] ?? [],
                        fn (Builder $q, array $families) => $q->whereIn('film_simulation', collect($families)
                            ->flatMap(fn (string $family) => FilmSimulation::ofFamily($family))
                            ->map->value),
                    )),
                SelectFilter::make('dynamic_range')
                    ->label('DR')
                    ->options(DynamicRange::class)
                    ->multiple(),
                SelectFilter::make('sensors')
                    ->label('Sensor')
                    ->relationship('sensors', 'label')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('source')
                    ->label('Origen')
                    ->options(RecipeSource::class),
                SelectFilter::make('color_chrome_effect')
                    ->label('CCE')
                    ->options(EffectLevel::class)
                    ->multiple(),
                SelectFilter::make('color_chrome_fx_blue')
                    ->label('FX Blue')
                    ->options(EffectLevel::class)
                    ->multiple(),
                SelectFilter::make('grain_roughness')
                    ->label('Grano')
                    ->options(EffectLevel::class)
                    ->multiple(),
                SelectFilter::make('wb_mode')
                    ->label('Modo de WB')
                    ->options(fn () => Recipe::query()->whereNotNull('wb_mode')->distinct()->orderBy('wb_mode')->pluck('wb_mode', 'wb_mode'))
                    ->multiple(),
                self::range('highlight', 'Highlight'),
                self::range('shadow', 'Shadow'),
                self::range('color', 'Color'),
                SelectFilter::make('tags')
                    ->label('Etiquetas')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload(),
                TernaryFilter::make('is_favorite')
                    ->label('Favorita'),
                TernaryFilter::make('needs_review')
                    ->label('Pendiente de revisión'),
                TernaryFilter::make('locked')
                    ->label('Con campos bloqueados')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('locked_fields')->where('locked_fields', '!=', '[]'),
                        false: fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('locked_fields')->orWhere('locked_fields', '[]')),
                    ),
                TernaryFilter::make('missing_from_source_at')
                    ->label('Desaparecida del origen')
                    ->nullable(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('compare')
                    ->label('Comparar')
                    ->icon(Heroicon::ArrowsRightLeft)
                    ->color('gray')
                    ->action(function (Collection $records, Component $livewire) {
                        if ($records->count() < CompareRecipes::MIN || $records->count() > CompareRecipes::MAX) {
                            Notification::make()->danger()->title('Selecciona de 2 a 4 recetas para comparar.')->send();

                            return;
                        }

                        $livewire->redirect(CompareRecipes::urlFor($records->sortBy('id')->modelKeys()));
                    }),
                BulkAction::make('addToCollection')
                    ->label('Añadir a colección')
                    ->icon(Heroicon::RectangleStack)
                    ->color('gray')
                    ->schema([
                        Select::make('collection_id')
                            ->label('Colección')
                            ->options(fn () => RecipeCollection::orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->createOptionForm([TextInput::make('name')->label('Nombre')->required()])
                            ->createOptionUsing(fn (array $data) => RecipeCollection::create($data)->getKey()),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $collection = RecipeCollection::findOrFail($data['collection_id']);

                        try {
                            $added = $collection->appendRecipes($records->modelKeys());
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->danger()->title('No se han añadido')->body($e->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title("Añadidas {$added} recetas a «{$collection->name}»")->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('tag')
                    ->label('Etiquetar')
                    ->icon(Heroicon::Tag)
                    ->color('gray')
                    ->schema([
                        Select::make('tags')
                            ->label('Etiquetas')
                            ->multiple()
                            ->options(fn () => Tag::orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->createOptionForm([TextInput::make('name')->label('Nombre')->required()->unique(Tag::class, 'name')])
                            ->createOptionUsing(fn (array $data) => Tag::create($data)->getKey()),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $records->each(fn (Recipe $recipe) => $recipe->tags()->syncWithoutDetaching($data['tags']));
                        Notification::make()->success()->title('Etiquetas añadidas a '.$records->count().' recetas')->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function number(string $field, string $label): TextColumn
    {
        return TextColumn::make($field)
            ->label($label)
            ->tooltip(RecipeFields::label($field))
            ->formatStateUsing(fn ($state) => RecipeFields::signed($state))
            ->placeholder('—')
            ->alignEnd()
            ->sortable();
    }

    /**
     * Filtro de rango min/max para un ajuste numérico.
     */
    private static function range(string $field, string $label): Filter
    {
        return Filter::make($field)
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('min')->label("{$label} mín.")->numeric(),
                    TextInput::make('max')->label("{$label} máx.")->numeric(),
                ]),
            ])
            ->query(fn (Builder $query, array $data) => $query
                ->when(filled($data['min'] ?? null), fn (Builder $q) => $q->where($field, '>=', (float) $data['min']))
                ->when(filled($data['max'] ?? null), fn (Builder $q) => $q->where($field, '<=', (float) $data['max'])))
            ->indicateUsing(fn (array $data) => array_values(array_filter([
                filled($data['min'] ?? null) ? "{$label} ≥ {$data['min']}" : null,
                filled($data['max'] ?? null) ? "{$label} ≤ {$data['max']}" : null,
            ])));
    }
}
