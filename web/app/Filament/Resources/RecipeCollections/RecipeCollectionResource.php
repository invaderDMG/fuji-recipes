<?php

namespace App\Filament\Resources\RecipeCollections;

use App\Filament\Resources\RecipeCollections\Pages\CreateRecipeCollection;
use App\Filament\Resources\RecipeCollections\Pages\EditRecipeCollection;
use App\Filament\Resources\RecipeCollections\Pages\ListRecipeCollections;
use App\Filament\Resources\RecipeCollections\Pages\ViewRecipeCollection;
use App\Filament\Resources\RecipeCollections\Schemas\RecipeCollectionForm;
use App\Filament\Resources\RecipeCollections\Schemas\RecipeCollectionInfolist;
use App\Filament\Resources\RecipeCollections\Tables\RecipeCollectionsTable;
use App\Models\RecipeCollection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecipeCollectionResource extends Resource
{
    protected static ?string $model = RecipeCollection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'colección';

    protected static ?string $pluralModelLabel = 'colecciones';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'collections';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return RecipeCollectionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecipeCollectionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecipeCollectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecipeCollections::route('/'),
            'create' => CreateRecipeCollection::route('/create'),
            'view' => ViewRecipeCollection::route('/{record}'),
            'edit' => EditRecipeCollection::route('/{record}/edit'),
        ];
    }
}
