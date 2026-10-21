<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Models\Recipe;
use App\Support\RecipeFields;
use App\Support\RecipeText;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;

class RecipeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Ajustes')
                    ->description('En el orden del menú de la cámara. 🔒 = editado a mano; la importación no lo sobrescribe.')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        self::setting('film_simulation', ['film_simulation'])
                            ->badge()
                            ->helperText(fn (Recipe $record) => $record->film_simulation?->value === $record->film_simulation_raw
                                ? null
                                : 'Original: '.($record->film_simulation_raw ?? '—')),
                        self::setting('grain_effect', ['grain_roughness', 'grain_size'], 'Grain Effect')
                            ->state(fn (Recipe $record) => $record->grainSummary()),
                        self::setting('color_chrome_effect', ['color_chrome_effect']),
                        self::setting('color_chrome_fx_blue', ['color_chrome_fx_blue']),
                        self::setting('white_balance', ['wb_mode', 'wb_kelvin', 'wb_shift_red', 'wb_shift_blue'], 'White Balance')
                            ->state(fn (Recipe $record) => $record->whiteBalanceSummary())
                            ->helperText(fn (Recipe $record) => $record->white_balance_raw ? "Original: {$record->white_balance_raw}" : null),
                        self::setting('dynamic_range', ['dynamic_range']),
                        self::setting('d_range_priority', ['d_range_priority']),
                        self::setting('highlight', ['highlight'])->formatStateUsing(self::signed(...)),
                        self::setting('shadow', ['shadow'])->formatStateUsing(self::signed(...)),
                        self::setting('color', ['color'])->formatStateUsing(self::signed(...)),
                        self::setting('sharpness', ['sharpness'])->formatStateUsing(self::signed(...)),
                        self::setting('high_iso_nr', ['high_iso_nr'])->formatStateUsing(self::signed(...)),
                        self::setting('clarity', ['clarity'])->formatStateUsing(self::signed(...)),
                        self::setting('iso_raw', ['iso_raw', 'iso_max']),
                        self::setting('exposure_compensation', ['exposure_compensation']),
                        self::setting('monochromatic_color', ['monochromatic_color'])
                            ->visible(fn (Recipe $record) => $record->monochromatic_color !== null),
                        self::setting('smooth_skin', ['smooth_skin'])
                            ->visible(fn (Recipe $record) => $record->smooth_skin !== null),
                        self::setting('tone_curve', ['tone_curve'])
                            ->visible(fn (Recipe $record) => $record->tone_curve !== null),
                    ]),
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        ImageEntry::make('image_url')
                            ->hiddenLabel()
                            ->imageWidth('100%')
                            ->imageHeight('auto')
                            ->visible(fn (Recipe $record) => $record->image_url !== null),
                        Section::make('Receta')
                            ->schema([
                                self::setting('author', ['author'], 'Autor'),
                                self::setting('published_at', ['published_at'], 'Publicada')->date(),
                                TextEntry::make('source')->label('Origen')->badge(),
                                TextEntry::make('sensors.label')->label('Sensores')->badge()->color('gray'),
                                TextEntry::make('tags.name')->label('Etiquetas')->badge()->placeholder('—'),
                                TextEntry::make('source_url')
                                    ->label('Post original')
                                    ->url(fn (?string $state) => str_starts_with((string) $state, 'http') ? $state : null, shouldOpenInNewTab: true)
                                    ->limit(50)
                                    ->placeholder('—'),
                            ]),
                        Section::make('Personal')
                            ->schema([
                                IconEntry::make('is_favorite')->label('Favorita')->boolean(),
                                TextEntry::make('rating')
                                    ->label('Valoración')
                                    ->formatStateUsing(fn (?int $state) => $state ? str_repeat('★', $state) : null)
                                    ->placeholder('—'),
                                TextEntry::make('tested_at')->label('Probada')->date()->placeholder('—'),
                                TextEntry::make('notes')->label('Notas')->placeholder('—')->prose(),
                            ]),
                        Section::make('Como texto')
                            ->description('Formato «Clave: valor» de fxw_import.py.')
                            ->collapsed()
                            ->schema([
                                TextEntry::make('as_text')
                                    ->hiddenLabel()
                                    ->state(fn (Recipe $record) => RecipeText::make($record))
                                    ->formatStateUsing(fn (string $state) => nl2br(e($state)))
                                    ->html()
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->copyableState(fn (Recipe $record) => RecipeText::make($record))
                                    ->copyMessage('Copiado'),
                            ]),
                        Section::make('Importación')
                            ->collapsed()
                            ->schema([
                                IconEntry::make('needs_review')->label('Pendiente de revisión')->boolean(),
                                TextEntry::make('locked_fields')
                                    ->label('Campos bloqueados')
                                    ->formatStateUsing(fn (string $state) => RecipeFields::label($state))
                                    ->badge()
                                    ->color('warning')
                                    ->placeholder('—'),
                                TextEntry::make('missing_from_source_at')->label('Desaparecida del origen')->dateTime()->placeholder('—'),
                                TextEntry::make('deleted_at')->label('Borrada')->dateTime()->placeholder('—')->color('danger'),
                                TextEntry::make('imported_at')->label('Importada')->dateTime()->placeholder('—'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Entrada de un ajuste, con candado si alguno de sus campos está bloqueado.
     *
     * @param  list<string>  $fields
     */
    private static function setting(string $name, array $fields, ?string $label = null): TextEntry
    {
        $locked = fn (Recipe $record) => array_intersect($fields, $record->locked_fields ?? []) !== [];

        return TextEntry::make($name)
            ->label($label ?? RecipeFields::label($fields[0]))
            ->placeholder('—')
            ->icon(fn (Recipe $record) => $locked($record) ? Heroicon::LockClosed : null)
            ->iconPosition(IconPosition::After)
            ->iconColor('warning');
    }

    private static function signed(mixed $state): ?string
    {
        return RecipeFields::signed($state);
    }
}
