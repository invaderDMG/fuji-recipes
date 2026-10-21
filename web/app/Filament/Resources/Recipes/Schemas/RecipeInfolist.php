<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Models\Recipe;
use App\Models\RecipePhoto;
use App\Support\RecipeFields;
use App\Support\RecipeText;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\HasLabel;
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
                Section::make('Revisión pendiente')
                    ->description('Valores que el origen no deja claros. Acepta la propuesta para tu cámara, corrígelos editando la receta o dalos por buenos.')
                    ->icon(Heroicon::ExclamationTriangle)
                    ->iconColor('warning')
                    ->columnSpanFull()
                    ->visible(fn (Recipe $record) => $record->needs_review)
                    ->schema([
                        RepeatableEntry::make('review_issues')
                            ->hiddenLabel()
                            ->state(fn (Recipe $record) => collect($record->review_issues ?? [])
                                ->map(fn (array $issue, string $field) => [
                                    'field' => RecipeFields::label($field),
                                    'reason' => $issue['reason'],
                                    'raw' => $issue['raw'],
                                    'current' => RecipeFields::display($record->{$field}),
                                    'suggestion' => $issue['suggestion'] ?? null,
                                ])->values()->all())
                            ->columns(5)
                            ->placeholder('Marcada a mano para revisar.')
                            ->schema([
                                TextEntry::make('field')->label('Ajuste')->weight('medium'),
                                TextEntry::make('reason')->label('Motivo')->badge()->color('warning'),
                                TextEntry::make('raw')->label('Texto original')->placeholder('—'),
                                TextEntry::make('current')->label('Valor actual'),
                                TextEntry::make('suggestion')->label('Propuesta para tu cámara')->placeholder('—')->color('success')->weight('bold'),
                            ]),
                    ]),
                Section::make('Ajustes')
                    ->description('En el orden del menú de la cámara. El candado marca lo editado a mano: la importación no lo sobrescribe.')
                    ->columnSpan(2)
                    ->schema([
                        ViewEntry::make('settings')
                            ->hiddenLabel()
                            ->view('filament.recipes.settings-grid')
                            ->viewData(fn (Recipe $record) => ['tiles' => self::tiles($record)]),
                    ]),
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        ViewEntry::make('image_url')
                            ->hiddenLabel()
                            ->view('filament.recipes.detail-photo'),
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
                                ViewEntry::make('rating')
                                    ->label('Valoración')
                                    ->view('filament.recipes.entry-rating'),
                                TextEntry::make('tested_at')->label('Probada')->date()->placeholder('—'),
                                TextEntry::make('notes')->label('Comentarios')->placeholder('—')->prose(),
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
                Section::make('Fotos de muestra')
                    ->description('Del post original en Fuji X Weekly. Pulsa una foto para verla a tamaño completo.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (Recipe $record) => $record->samplePhotos->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('samplePhotos')
                            ->hiddenLabel()
                            ->contained(false)
                            ->grid(['default' => 2, 'md' => 3, 'xl' => 4])
                            ->schema([
                                ImageEntry::make('thumbnail')
                                    ->hiddenLabel()
                                    ->state(fn (RecipePhoto $record) => $record->thumbnail())
                                    ->url(fn (RecipePhoto $record) => $record->src(), shouldOpenInNewTab: true)
                                    ->imageWidth('100%')
                                    ->imageHeight('auto')
                                    ->extraImgAttributes(['loading' => 'lazy', 'class' => 'rounded-lg']),
                                TextEntry::make('caption')
                                    ->hiddenLabel()
                                    ->size('xs')
                                    ->color('gray'),
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

    /**
     * Casillas de la cuadrícula de ajustes; las opcionales solo si la receta las usa.
     *
     * @return list<array{key: string, label: string, value: ?string, note: ?string, locked: bool}>
     */
    public static function tiles(Recipe $record): array
    {
        $signed = fn (string $field) => fn (Recipe $r) => RecipeFields::signed($r->{$field});
        $plain = fn (string $field) => fn (Recipe $r) => match (true) {
            $r->{$field} instanceof HasLabel => $r->{$field}->getLabel(),
            blank($r->{$field}) => null,
            default => RecipeFields::display($r->{$field}),
        };

        $tiles = [
            ['film_simulation', ['film_simulation'], fn (Recipe $r) => $r->film_simulation?->label() ?? $r->film_simulation_raw,
                fn (Recipe $r) => $r->film_simulation?->value === $r->film_simulation_raw ? null : 'Original: '.($r->film_simulation_raw ?? '—')],
            ['monochromatic_color', ['monochromatic_color'], $plain('monochromatic_color'), null, true],
            ['grain_effect', ['grain_roughness', 'grain_size'], fn (Recipe $r) => $r->grainSummary()],
            ['color_chrome_effect', ['color_chrome_effect'], $plain('color_chrome_effect')],
            ['color_chrome_fx_blue', ['color_chrome_fx_blue'], $plain('color_chrome_fx_blue')],
            ['smooth_skin', ['smooth_skin'], $plain('smooth_skin'), null, true],
            ['white_balance', ['wb_mode', 'wb_kelvin', 'wb_shift_red', 'wb_shift_blue'], fn (Recipe $r) => $r->whiteBalanceSummary(),
                fn (Recipe $r) => $r->white_balance_raw ? "Original: {$r->white_balance_raw}" : null],
            ['dynamic_range', ['dynamic_range'], fn (Recipe $r) => $r->dynamic_range?->label()],
            ['d_range_priority', ['d_range_priority'], $plain('d_range_priority')],
            ['tone_curve', ['tone_curve'], $plain('tone_curve'), null, true],
            ['highlight', ['highlight'], $signed('highlight')],
            ['shadow', ['shadow'], $signed('shadow')],
            ['color', ['color'], $signed('color')],
            ['sharpness', ['sharpness'], $signed('sharpness')],
            ['high_iso_nr', ['high_iso_nr'], $signed('high_iso_nr')],
            ['clarity', ['clarity'], $signed('clarity')],
            ['iso_raw', ['iso_raw', 'iso_max'], $plain('iso_raw')],
            ['exposure_compensation', ['exposure_compensation'], $plain('exposure_compensation')],
        ];

        $locked = $record->locked_fields ?? [];
        $result = [];

        foreach ($tiles as $tile) {
            [$key, $fields, $value, $note, $optional] = $tile + [3 => null, 4 => false];
            $value = $value($record);

            if ($optional && $value === null) {
                continue;
            }

            $result[] = [
                'key' => $key,
                'label' => RecipeFields::label($fields[0]),
                'value' => $value,
                'note' => $note ? $note($record) : null,
                'locked' => array_intersect($fields, $locked) !== [],
            ];
        }

        return $result;
    }
}
