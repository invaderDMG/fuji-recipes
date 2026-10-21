<?php

namespace App\Filament\Resources\Sensors;

use App\Filament\Resources\Sensors\Pages\ListSensors;
use App\Filament\Resources\Sensors\Tables\SensorsTable;
use App\Models\Sensor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Sensores de solo lectura: los crea SensorSeeder o la importación.
 */
class SensorResource extends Resource
{
    protected static ?string $model = Sensor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $modelLabel = 'sensor';

    protected static ?string $pluralModelLabel = 'sensores';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return SensorsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSensors::route('/'),
        ];
    }
}
