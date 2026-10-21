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
use App\Support\MyCamera;
use App\Support\RecipeFields;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Component;

class RecipesTable
{
    public static function configure(Table $table): Table
    {
        $mySensor = MyCamera::sensorSlug();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('sensors'))
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
                IconColumn::make('compatible')
                    ->label('')
                    ->state(fn (Recipe $record) => $record->isCompatibleWith($mySensor))
                    ->boolean()
                    ->trueIcon('heroicon-s-camera')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-no-symbol')
                    ->falseColor('gray')
                    ->tooltip(fn (Recipe $record) => $record->isCompatibleWith($mySensor)
                        ? 'Compatible con '.MyCamera::label()
                        : 'No figura para '.MyCamera::label()),
                TextColumn::make('film_simulation')
                    ->label('Simulación')
                    ->badge()
                    ->placeholder($placeholder = '—')
                    ->sortable(),
                TextColumn::make('review_issues')
                    ->label('Pendiente')
                    ->state(fn (Recipe $record) => collect($record->review_issues ?? [])
                        ->map(fn (array $issue, string $field) => RecipeFields::reviewLine($field, $issue))
                        ->values()->all())
                    ->listWithLineBreaks()
                    ->color('warning')
                    ->wrap()
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? null) === 'review'),
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
                    ->formatStateUsing(fn (?int $state) => RecipeFields::rating($state))
                    ->placeholder($placeholder)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('Comentarios')
                    ->limit(60)
                    ->tooltip(fn (Recipe $record) => $record->notes)
                    ->placeholder($placeholder)
                    ->toggleable(isToggledHiddenByDefault: true),
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
                        fn (Builder $q, array $families) => $q->inFamilies($families),
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
                self::range('rating', 'Valoración'),
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
                        true: fn (Builder $q) => $q->withLockedFields(),
                        false: fn (Builder $q) => $q->withLockedFields(false),
                    ),
                TernaryFilter::make('missing_from_source_at')
                    ->label('Desaparecida del origen')
                    ->nullable(),
                TrashedFilter::make()
                    ->label('Borradas'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                Action::make('acceptSuggestions')
                    ->label('Aceptar propuesta')
                    ->icon(Heroicon::Check)
                    ->color('success')
                    ->visible(fn (Recipe $record) => collect($record->review_issues ?? [])->contains(fn ($i) => filled($i['suggestion'] ?? null)))
                    ->action(function (Recipe $record) {
                        $record->acceptSuggestions();
                        Notification::make()->success()->title("Propuesta aceptada: {$record->name}")->send();
                    }),
                Action::make('markReviewed')
                    ->label('Dar por buena')
                    ->icon(Heroicon::CheckBadge)
                    ->color('gray')
                    ->visible(fn (Recipe $record) => $record->needs_review)
                    ->requiresConfirmation()
                    ->modalDescription('Los valores actuales se dan por buenos y quedan bloqueados frente a la importación.')
                    ->action(fn (Recipe $record) => $record->markReviewed()),
                ViewAction::make(),
                EditAction::make(),
                RestoreAction::make(),
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
                BulkAction::make('acceptSuggestions')
                    ->label('Aceptar propuestas')
                    ->icon(Heroicon::Check)
                    ->color('gray')
                    ->action(function (Collection $records) {
                        $accepted = $records->sum(fn (Recipe $recipe) => $recipe->acceptSuggestions());
                        Notification::make()->success()->title("Propuestas aceptadas: {$accepted}")->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('markReviewed')
                    ->label('Dar por buenas')
                    ->icon(Heroicon::CheckBadge)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Los valores actuales se dan por buenos y quedan bloqueados frente a la importación.')
                    ->action(fn (Collection $records) => $records->each->markReviewed())
                    ->deselectRecordsAfterCompletion(),
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
                    // Borrado lógico: se conservan y la importación no las vuelve a crear
                    DeleteBulkAction::make()
                        ->modalDescription('Las recetas se conservan como borradas (filtro «Borradas») y la importación no las vuelve a crear.'),
                    RestoreBulkAction::make(),
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
            ->query(fn (Builder $query, array $data) => $query->inRange($field, $data['min'] ?? null, $data['max'] ?? null))
            ->indicateUsing(fn (array $data) => array_values(array_filter([
                filled($data['min'] ?? null) ? "{$label} ≥ {$data['min']}" : null,
                filled($data['max'] ?? null) ? "{$label} ≤ {$data['max']}" : null,
            ])));
    }
}
