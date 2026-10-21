<?php

namespace App\Filament\Resources\Recipes\RelationManagers;

use App\Enums\PhotoSource;
use App\Models\RecipePhoto;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Fotos propias hechas con la receta. Se guardan en storage/app/public/photos.
 */
class MyPhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'myPhotos';

    protected static ?string $title = 'Mis fotos';

    protected static ?string $modelLabel = 'foto';

    protected static ?string $pluralModelLabel = 'fotos';

    // También se pueden subir fotos desde la ficha, no solo desde el formulario de edición
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('path')
                    ->label('Foto')
                    ->disk(RecipePhoto::DISK)
                    ->directory(RecipePhoto::DIRECTORY)
                    ->image()
                    ->maxSize(51200)
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('caption')
                    ->label('Pie de foto')
                    ->rows(2),
                DatePicker::make('taken_at')
                    ->label('Tomada el'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    ImageColumn::make('path')
                        ->disk(RecipePhoto::DISK)
                        ->imageHeight(220)
                        ->imageWidth('100%')
                        ->extraImgAttributes(['class' => 'object-cover rounded-lg', 'loading' => 'lazy']),
                    TextColumn::make('caption')
                        ->color('gray')
                        ->wrap(),
                    TextColumn::make('taken_at')
                        ->date()
                        ->size('xs')
                        ->color('gray'),
                ])->space(2),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->recordUrl(fn (RecipePhoto $record) => $record->src(), shouldOpenInNewTab: true)
            ->headerActions([
                CreateAction::make()
                    ->label('Subir foto')
                    ->icon(Heroicon::ArrowUpTray)
                    ->mutateDataUsing(fn (array $data) => [
                        ...$data,
                        'source' => PhotoSource::Mine,
                        'position' => (int) $this->getOwnerRecord()->myPhotos()->max('position') + 1,
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Aún no has subido fotos con esta receta')
            ->emptyStateIcon(Heroicon::OutlinedCamera)
            ->paginated(false);
    }
}
