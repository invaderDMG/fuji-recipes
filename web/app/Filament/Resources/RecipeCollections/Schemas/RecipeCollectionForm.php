<?php

namespace App\Filament\Resources\RecipeCollections\Schemas;

use App\Models\Recipe;
use App\Models\RecipeCollection;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RecipeCollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(4),
                    ]),
                Section::make('Slots C1–C7')
                    ->description('Arrastra para reordenar: la primera receta va en C1.')
                    ->columnSpan(2)
                    ->schema([
                        // Las posiciones las guardan las páginas con RecipeCollection::setRecipes()
                        Repeater::make('slots')
                            ->hiddenLabel()
                            ->simple(
                                Select::make('recipe_id')
                                    ->options(fn () => Recipe::orderBy('name')->get()
                                        ->mapWithKeys(fn (Recipe $r) => [$r->id => $r->name.($r->film_simulation ? " · {$r->film_simulation->value}" : '')]))
                                    ->searchable()
                                    ->required()
                                    ->distinct(),
                            )
                            ->reorderable()
                            ->maxItems(RecipeCollection::MAX_SLOTS)
                            ->addActionLabel('Añadir receta')
                            ->defaultItems(0),
                    ]),
            ]);
    }
}
