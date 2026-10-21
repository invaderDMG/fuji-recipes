<?php

namespace App\Filament\Resources\Tags\Tables;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Tag;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('recipes'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('recipes_count')
                    ->label('Recetas')
                    ->sortable()
                    ->url(fn (Tag $record) => RecipeResource::getUrl('index', ['filters' => ['tags' => ['values' => [$record->id]]]])),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
