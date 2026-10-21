<?php

use App\Capture\CaptureReader;
use App\Capture\CaptureResult;
use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\GrainSize;
use App\Enums\RecipeSource;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\SensorSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

/**
 * Salida del script de Python para la receta de IMG_4263 («Last Summer»).
 */
function captureJson(array $recipe = [], ?string $name = 'Last Summer', ?string $author = '@De Luca Emanuele'): string
{
    return json_encode([
        'recipe' => [
            'film_simulation' => 'Classic Negative', 'dynamic_range' => 'DR400', 'd_range_priority' => null,
            'grain_roughness' => 'Strong', 'grain_size' => 'Small',
            'color_chrome_effect' => 'Strong', 'color_chrome_fx_blue' => 'Weak',
            'white_balance' => 'Auto, +3 Red & -5 Blue', 'wb_mode' => 'Auto', 'wb_kelvin' => null,
            'wb_shift_red' => 3, 'wb_shift_blue' => -5, 'tone_curve' => null,
            'highlight' => -2.0, 'shadow' => 0.0, 'color' => 4.0, 'monochromatic_color' => null,
            'sharpness' => 0.0, 'high_iso_nr' => -4.0, 'clarity' => -2.0, 'smooth_skin' => null,
            'iso' => null, 'iso_max' => null, 'exposure_compensation' => null,
            'raw_json' => '{"film simulation": "Classic Negative"}',
            ...$recipe,
        ],
        'name' => $name,
        'author' => $author,
        'lines' => ['LAST SUMMER', 'Film Simulation Classic Negative'],
    ]);
}

beforeEach(function () {
    $this->seed(SensorSeeder::class);
});

describe('CaptureReader', function () {
    it('maps the output of the Python reader to recipe attributes', function () {
        Process::fake(['*' => Process::result(captureJson())]);

        $result = app(CaptureReader::class)->fromImage('/tmp/x.png');

        expect($result->recognized())->toBeTrue()
            ->and($result->attributes['name'])->toBe('Last Summer')
            ->and($result->attributes['author'])->toBe('De Luca Emanuele')
            ->and($result->attributes['film_simulation'])->toBe(FilmSimulation::ClassicNegative)
            ->and($result->attributes['color_chrome_fx_blue'])->toBe(EffectLevel::Weak)
            ->and($result->attributes['wb_shift_red'])->toBe(3)
            ->and($result->attributes['raw_json'])->toBe(['film simulation' => 'Classic Negative'])
            ->and($result->reviewIssues)->toBe([]);

        Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'recipe_from_capture.py'));
    });

    it('proposes the value for my camera when the text gives one per sensor', function () {
        Process::fake(['*' => Process::result(captureJson(['color_chrome_fx_blue' => 'Strong (X-Trans IV), Weak (X-Trans V)']))]);

        $result = app(CaptureReader::class)->fromText('…');

        expect($result->reviewIssues['color_chrome_fx_blue']['suggestion'])->toBe('Weak');
    });

    it('reports when there is no recipe and when the reader fails', function () {
        Process::fake(['*' => Process::result(json_encode(['recipe' => null, 'name' => null, 'author' => null, 'lines' => ['hola']]))]);
        expect(app(CaptureReader::class)->fromText('hola')->recognized())->toBeFalse();

        Process::fake(['*' => Process::result(output: '', errorOutput: "Traceback…\nRuntimeError: imagen vacía", exitCode: 1)]);
        expect(fn () => app(CaptureReader::class)->fromImage('/tmp/x.png'))
            ->toThrow(RuntimeException::class, 'No se pudo leer la captura: RuntimeError: imagen vacía');
    });

    it('explains how to install Python when it is missing', function () {
        config(['fuji.capture.python' => '/nonexistent/python']);

        expect(fn () => app(CaptureReader::class)->fromText('x'))->toThrow(RuntimeException::class, 'make setup-python');
    });
});

describe('OCR real con las capturas de muestra', function () {
    beforeEach(function () {
        $python = config('fuji.capture.python');
        $hasVision = is_executable($python) && Process::run([$python, '-c', 'import Vision'])->successful();

        if (! $hasVision || ! is_file(base_path('../IMG_4262.webp'))) {
            $this->markTestSkipped('Necesita las capturas de muestra en la raíz y pyobjc-framework-Vision en .venv.');
        }
    });

    it('reads the sample screenshots', function (string $file, array $expected) {
        $result = app(CaptureReader::class)->fromImage(base_path("../{$file}"));

        expect($result->recognized())->toBeTrue()
            ->and($result->reviewIssues)->toBe([]);
        foreach ($expected as $field => $value) {
            expect($result->attributes[$field])->toEqual($value, "{$file}: {$field}");
        }
    })->with([
        'TikTok con fondo de fotos (sin nombre)' => ['IMG_4262.webp', [
            'name' => null, 'film_simulation' => FilmSimulation::ClassicNegative, 'dynamic_range' => DynamicRange::DR400,
            'grain_roughness' => EffectLevel::Strong, 'grain_size' => GrainSize::Small,
            'color_chrome_effect' => EffectLevel::Off, 'color_chrome_fx_blue' => EffectLevel::Off,
            'wb_mode' => 'Daylight', 'wb_shift_red' => 4, 'wb_shift_blue' => -5,
            'highlight' => -2, 'shadow' => 1, 'color' => 3, 'sharpness' => -2, 'high_iso_nr' => -4, 'clarity' => -2,
        ]],
        'vídeo con título y crédito' => ['IMG_4263.webp', [
            'name' => 'Last Summer', 'author' => 'De Luca Emanuele', 'film_simulation' => FilmSimulation::ClassicNegative,
            'grain_roughness' => EffectLevel::Strong, 'grain_size' => GrainSize::Small,
            'color_chrome_effect' => EffectLevel::Strong, 'color_chrome_fx_blue' => EffectLevel::Weak,
            'wb_mode' => 'Auto', 'wb_shift_red' => 3, 'wb_shift_blue' => -5, 'dynamic_range' => DynamicRange::DR400,
            'highlight' => -2, 'shadow' => 0, 'color' => 4, 'sharpness' => 0, 'high_iso_nr' => -4, 'clarity' => -2,
        ]],
        'tabla etiqueta | valor con ISO' => ['IMG_4264.webp', [
            'film_simulation' => FilmSimulation::ClassicNegative, 'grain_roughness' => EffectLevel::Strong,
            'wb_mode' => 'Daylight', 'wb_shift_red' => 4, 'wb_shift_blue' => -5,
            'high_iso_nr' => -4, 'clarity' => -2, 'iso_raw' => 'up to ISO 6400', 'iso_max' => 6400,
        ]],
        'texto con «por» autor y WB en otro orden' => ['IMG_4265.webp', [
            'name' => 'Classic Cuban Negative', 'author' => 'Osan Bilgi', 'grain_size' => GrainSize::Large,
            'color_chrome_fx_blue' => EffectLevel::Strong, 'wb_mode' => 'Auto', 'wb_shift_red' => 4, 'wb_shift_blue' => -5,
            'shadow' => 1, 'color' => 4, 'sharpness' => 0, 'clarity' => -4,
        ]],
    ]);

    it('creates a recipe from an uploaded screenshot, end to end', function () {
        $this->actingAs(User::factory()->create());
        $upload = UploadedFile::fake()->createWithContent('IMG_4265.webp', file_get_contents(base_path('../IMG_4265.webp')));

        Livewire::test(ListRecipes::class)
            ->callAction('fromCapture', ['image' => $upload])
            ->assertHasNoActionErrors()
            ->assertRedirect('/recipes/create?capture=1');

        Livewire::test(CreateRecipe::class)
            ->assertSchemaStateSet(['name' => 'Classic Cuban Negative', 'author' => 'Osan Bilgi', 'clarity' => -4])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Recipe::where('name', 'Classic Cuban Negative')->value('grain_size'))->toBe(GrainSize::Large);
    });

    it('reads pasted text', function () {
        $result = app(CaptureReader::class)->fromText("Film Simulation: Reala Ace\nGrain Effect: Weak, Small\nWhite Balance: 5200K, +2 Red & -4 Blue\nHighlight: -1\nShadow: -0.5\n");

        expect($result->attributes['film_simulation'])->toBe(FilmSimulation::RealaAce)
            ->and($result->attributes['wb_mode'])->toBe('Kelvin')
            ->and($result->attributes['wb_kelvin'])->toBe(5200)
            ->and($result->attributes['shadow'])->toBe(-0.5);
    });
});

describe('interfaz', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
    });

    it('reads a capture from the list and opens the prefilled form', function () {
        Process::fake(['*' => Process::result(captureJson())]);

        Livewire::test(ListRecipes::class)
            ->callAction('fromCapture', ['text' => 'Film Simulation: Classic Negative…'])
            ->assertHasNoActionErrors()
            ->assertRedirect('/recipes/create?capture=1');

        expect(session(CreateRecipe::CAPTURE_SESSION_KEY))->toBeInstanceOf(CaptureResult::class);
    });

    it('warns when nothing is recognized and stays on the list', function () {
        Process::fake(['*' => Process::result(json_encode(['recipe' => null, 'name' => null, 'author' => null, 'lines' => ['una foto sin receta']]))]);

        Livewire::test(ListRecipes::class)
            ->callAction('fromCapture', ['text' => 'nada'])
            ->assertNoRedirect()
            ->assertNotified('No encuentro una receta');
    });

    it('requires an image or a text', function () {
        Livewire::test(ListRecipes::class)
            ->callAction('fromCapture', [])
            ->assertHasActionErrors(['image']);
    });

    it('creates the recipe from the capture, with my sensor and the OCR raw data', function () {
        Process::fake(['*' => Process::result(captureJson(['color_chrome_fx_blue' => 'Strong (X-Trans IV), Weak (X-Trans V)']))]);
        session()->put(CreateRecipe::CAPTURE_SESSION_KEY, app(CaptureReader::class)->fromText('…'));

        Livewire::test(CreateRecipe::class)
            ->assertSchemaStateSet([
                'name' => 'Last Summer',
                'film_simulation' => FilmSimulation::ClassicNegative,
                'color_chrome_fx_blue' => EffectLevel::Weak,
                'wb_shift_blue' => -5,
            ])
            ->assertSee(['Leído de la captura', 'FX Blue (ambiguo)', 'LAST SUMMER'])
            ->call('create')
            ->assertHasNoFormErrors();

        $recipe = Recipe::where('name', 'Last Summer')->firstOrFail();
        expect($recipe->source)->toBe(RecipeSource::Manual)
            ->and($recipe->author)->toBe('De Luca Emanuele')
            ->and($recipe->color_chrome_fx_blue)->toBe(EffectLevel::Weak)
            ->and($recipe->white_balance_raw)->toBe('Auto, +3 Red & -5 Blue')
            ->and($recipe->raw_json)->toBe(['film simulation' => 'Classic Negative'])
            ->and($recipe->sensors->pluck('slug')->all())->toBe(['x-trans-v'])
            ->and(session()->has(CreateRecipe::CAPTURE_SESSION_KEY))->toBeFalse();
    });

    it('warns when the recipe already exists', function () {
        Process::fake(['*' => Process::result(captureJson())]);
        $result = app(CaptureReader::class)->fromText('…');
        Recipe::factory()->create(['name' => 'Ya la tengo', ...collect($result->attributes)->except(['name', 'author', 'raw_json'])->all()]);
        session()->put(CreateRecipe::CAPTURE_SESSION_KEY, $result);

        Livewire::test(CreateRecipe::class)
            ->assertSee('Ya tienes esta receta: Ya la tengo')
            ->assertSee('Ya la tengo · 0');
    });

    it('opens the normal form without a capture', function () {
        Livewire::test(CreateRecipe::class)->assertDontSee('Leído de la captura');
    });
});
