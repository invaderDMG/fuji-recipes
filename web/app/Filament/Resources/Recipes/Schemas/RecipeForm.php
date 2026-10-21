<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\GrainSize;
use App\Models\Recipe;
use App\Support\MyCamera;
use App\Support\RecipeFields;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RecipeForm
{
    public const WB_MODES = [
        'Auto', 'Auto White Priority', 'Auto Ambience Priority', 'Daylight', 'Shade',
        'Fluorescent 1', 'Fluorescent 2', 'Fluorescent 3', 'Incandescent', 'Underwater',
        'Kelvin', 'Custom',
    ];

    public const D_RANGE_PRIORITY = ['Off', 'Weak', 'Strong', 'Auto'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Callout::make(fn ($livewire) => 'Ya tienes esta receta: '.($livewire->capture['similar'][0]['name'] ?? ''))
                    ->description('Distancia 0 con una receta existente. Revisa si de verdad quieres crear otra.')
                    ->warning()
                    ->columnSpanFull()
                    ->visible(fn ($livewire) => isset($livewire->capture['similar'][0]) && (float) $livewire->capture['similar'][0]['distance'] === 0.0),
                Section::make('Leído de la captura')
                    ->description('Revisa los ajustes antes de guardar: el OCR puede equivocarse.')
                    ->icon(Heroicon::Photo)
                    ->columnSpanFull()
                    ->collapsible()
                    ->columns(3)
                    ->visible(fn ($livewire) => filled($livewire->capture ?? null))
                    ->schema([
                        TextEntry::make('capture_similar')
                            ->label('Las más parecidas')
                            ->state(fn ($livewire) => collect($livewire->capture['similar'] ?? [])
                                ->map(fn (array $r) => "{$r['name']} · {$r['distance']}")->all())
                            ->listWithLineBreaks()
                            ->placeholder('—'),
                        TextEntry::make('capture_issues')
                            ->label('Campos dudosos')
                            ->state(fn ($livewire) => $livewire->capture['issues'] ?? [])
                            ->listWithLineBreaks()
                            ->color('warning')
                            ->placeholder('Ninguno'),
                        TextEntry::make('capture_lines')
                            ->label('Texto leído')
                            ->state(fn ($livewire) => $livewire->capture['lines'] ?? [])
                            ->listWithLineBreaks()
                            ->limitList(12)
                            ->expandableLimitedList()
                            ->size('xs')
                            ->color('gray'),
                    ]),
                Section::make('Ajustes')
                    ->description('En el orden del menú de la cámara. En las recetas importadas, los campos que cambies quedan bloqueados frente a la importación.')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        Select::make('film_simulation')
                            ->label(RecipeFields::label('film_simulation'))
                            ->options(FilmSimulation::class),
                        Select::make('grain_roughness')
                            ->label(RecipeFields::label('grain_roughness'))
                            ->options(EffectLevel::class)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, $state) => self::grainOff($state) ? $set('grain_size', null) : null),
                        Select::make('grain_size')
                            ->label(RecipeFields::label('grain_size'))
                            ->options(GrainSize::class)
                            ->helperText('Solo cuenta con grano Weak o Strong.')
                            ->disabled(fn (Get $get) => self::grainOff($get('grain_roughness')))
                            ->dehydrated(),
                        Select::make('color_chrome_effect')
                            ->label(RecipeFields::label('color_chrome_effect'))
                            ->options(EffectLevel::class),
                        Select::make('color_chrome_fx_blue')
                            ->label(RecipeFields::label('color_chrome_fx_blue'))
                            ->options(EffectLevel::class),
                        Select::make('smooth_skin')
                            ->label(RecipeFields::label('smooth_skin'))
                            ->options(EffectLevel::class),
                        Select::make('wb_mode')
                            ->label(RecipeFields::label('wb_mode'))
                            ->options(array_combine(self::WB_MODES, self::WB_MODES))
                            ->live(),
                        self::range('wb_kelvin')
                            ->suffix('K')
                            ->visible(fn (Get $get) => $get('wb_mode') === 'Kelvin')
                            ->required(fn (Get $get) => $get('wb_mode') === 'Kelvin'),
                        self::range('wb_shift_red'),
                        self::range('wb_shift_blue'),
                        Select::make('dynamic_range')
                            ->label(RecipeFields::label('dynamic_range'))
                            ->options(DynamicRange::class),
                        Select::make('d_range_priority')
                            ->label(RecipeFields::label('d_range_priority'))
                            ->options(array_combine(self::D_RANGE_PRIORITY, self::D_RANGE_PRIORITY)),
                        TextInput::make('tone_curve')
                            ->label(RecipeFields::label('tone_curve')),
                        self::range('highlight'),
                        self::range('shadow'),
                        self::range('color'),
                        TextInput::make('monochromatic_color')
                            ->label(RecipeFields::label('monochromatic_color')),
                        self::range('sharpness'),
                        self::range('high_iso_nr'),
                        self::range('clarity'),
                        TextInput::make('iso_raw')
                            ->label(RecipeFields::label('iso_raw'))
                            ->placeholder('Auto up to ISO 6400'),
                        TextInput::make('iso_max')
                            ->label(RecipeFields::label('iso_max'))
                            ->integer()
                            ->minValue(80)
                            ->maxValue(51200),
                        TextInput::make('exposure_compensation')
                            ->label(RecipeFields::label('exposure_compensation'))
                            ->placeholder('+1/3 to +1 (typically)'),
                    ]),
                Section::make('Receta')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('author')
                            ->label('Autor')
                            ->maxLength(255),
                        DatePicker::make('published_at')
                            ->label('Publicada'),
                        TextInput::make('image_url')
                            ->label('URL de la imagen')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('source_url')
                            ->label('URL de referencia')
                            ->url()
                            ->maxLength(255)
                            ->visibleOn('create'),
                        Select::make('sensors')
                            ->label('Sensores')
                            ->relationship('sensors', 'label')
                            ->multiple()
                            ->preload()
                            // Las recetas nuevas son, por defecto, para mi cámara
                            ->default(fn () => array_filter([MyCamera::sensor()?->id])),
                        Select::make('tags')
                            ->label('Etiquetas')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->preload()
                            ->createOptionForm([TextInput::make('name')->label('Nombre')->required()->unique('tags', 'name')]),
                        Toggle::make('is_favorite')
                            ->label('Favorita'),
                        Select::make('rating')
                            ->label('Valoración (0–'.Recipe::RATING_MAX.')')
                            ->options(RecipeFields::ratingOptions()),
                        DatePicker::make('tested_at')
                            ->label('Probada'),
                        Textarea::make('notes')
                            ->label('Comentarios')
                            ->rows(4),
                        Toggle::make('needs_review')
                            ->label('Pendiente de revisión')
                            ->visibleOn('edit'),
                        CheckboxList::make('locked_fields')
                            ->label('Campos bloqueados')
                            ->helperText('Desmarca un campo para que la próxima importación vuelva a traer su valor del origen.')
                            ->options(fn (?Recipe $record) => RecipeFields::options($record?->locked_fields ?? []))
                            ->visible(fn (?Recipe $record) => filled($record?->locked_fields)),
                    ]),
            ]);
    }

    private static function grainOff(mixed $roughness): bool
    {
        return in_array($roughness, [null, '', EffectLevel::Off, EffectLevel::Off->value], true);
    }

    /**
     * Campo numérico con el rango y el paso de Recipe::RANGES.
     */
    private static function range(string $field): TextInput
    {
        [$min, $max, $step] = Recipe::RANGES[$field];

        return TextInput::make($field)
            ->label(RecipeFields::label($field))
            ->numeric()
            ->minValue($min)
            ->maxValue($max)
            ->step($step)
            ->rule("multiple_of:{$step}")
            ->helperText("{$min} … {$max}, paso {$step}");
    }
}
