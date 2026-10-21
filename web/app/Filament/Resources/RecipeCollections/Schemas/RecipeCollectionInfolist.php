<?php

namespace App\Filament\Resources\RecipeCollections\Schemas;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use App\Models\RecipeCollection;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RecipeCollectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('name')->label('Nombre'),
                        TextEntry::make('description')->label('Descripción')->placeholder('—'),
                    ]),
                Section::make('Slots C1–C7')
                    ->columnSpan(2)
                    ->schema([
                        RepeatableEntry::make('recipes')
                            ->hiddenLabel()
                            ->columns(4)
                            ->placeholder('Sin recetas')
                            ->schema([
                                TextEntry::make('pivot.position')
                                    ->label('Slot')
                                    ->formatStateUsing(fn (int $state) => "C{$state}")
                                    ->badge(),
                                TextEntry::make('name')
                                    ->label('Receta')
                                    ->url(fn (Recipe $record) => RecipeResource::getUrl('view', ['record' => $record])),
                                TextEntry::make('film_simulation')->label('Simulación')->badge()->placeholder('—'),
                                TextEntry::make('white_balance')
                                    ->label('WB')
                                    ->state(fn (Recipe $record) => $record->whiteBalanceSummary())
                                    ->placeholder('—'),
                            ]),
                        TextEntry::make('free_slots')
                            ->hiddenLabel()
                            ->state(fn (RecipeCollection $record) => ($free = RecipeCollection::MAX_SLOTS - $record->recipes->count())
                                ? "{$free} slots libres"
                                : 'Colección completa'),
                    ]),
            ]);
    }
}
