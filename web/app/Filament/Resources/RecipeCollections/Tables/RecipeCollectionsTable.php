<?php

namespace App\Filament\Resources\RecipeCollections\Tables;

use App\Models\RecipeCollection;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecipeCollectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('recipes'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn (RecipeCollection $record) => $record->description)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('recipes.name')
                    ->label('Recetas (C1…C7)')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->placeholder('—'),
                TextColumn::make('slots')
                    ->label('Slots')
                    ->state(fn (RecipeCollection $record) => $record->recipes->count().' / '.RecipeCollection::MAX_SLOTS),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
