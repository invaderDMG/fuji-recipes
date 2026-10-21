<?php

use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\RecipeSource;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Resources\Recipes\Pages\ViewRecipe;
use App\Filament\Resources\Recipes\Widgets\SimilarRecipes;
use App\Models\Recipe;
use App\Models\Sensor;
use App\Models\User;
use Database\Seeders\SensorSeeder;
use Livewire\Livewire;
use Tests\Fixtures\GoldenRecipes;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->seed(SensorSeeder::class);
});

it('renders the list, view, edit and create pages', function () {
    $recipe = Recipe::factory()->create(['name' => 'Kodak Gold']);

    $this->get('/recipes')->assertOk()->assertSee('Kodak Gold');
    $this->get("/recipes/{$recipe->id}")->assertOk()->assertSee('Kodak Gold')->assertSee('Film Simulation');
    $this->get("/recipes/{$recipe->id}/edit")->assertOk();
    $this->get('/recipes/create')->assertOk();
});

it('filters by a highlight range', function () {
    $low = Recipe::factory()->create(['highlight' => -1.5]);
    $mid = Recipe::factory()->create(['highlight' => 0]);
    $high = Recipe::factory()->create(['highlight' => 2]);

    Livewire::test(ListRecipes::class)
        ->filterTable('highlight', ['min' => -1, 'max' => 1])
        ->assertCanSeeTableRecords([$mid])
        ->assertCanNotSeeTableRecords([$low, $high])
        ->filterTable('highlight', ['min' => null, 'max' => -1])
        ->assertCanSeeTableRecords([$low])
        ->assertCanNotSeeTableRecords([$mid, $high]);
});

it('finds X-Trans V Classic Negative recipes with highlight ≤ −1', function () {
    $xtv = Sensor::where('slug', 'x-trans-v')->firstOrFail();
    $match = Recipe::factory()->create(['film_simulation' => FilmSimulation::ClassicNegative, 'highlight' => -1.5]);
    $otherSensor = Recipe::factory()->create(['film_simulation' => FilmSimulation::ClassicNegative, 'highlight' => -2]);
    $otherSim = Recipe::factory()->create(['film_simulation' => FilmSimulation::ClassicChrome, 'highlight' => -2]);
    $tooHigh = Recipe::factory()->create(['film_simulation' => FilmSimulation::ClassicNegative, 'highlight' => 0]);
    $match->sensors()->attach($xtv);
    $otherSim->sensors()->attach($xtv);
    $tooHigh->sensors()->attach($xtv);
    $otherSensor->sensors()->attach(Sensor::where('slug', 'x-trans-iv')->firstOrFail());

    Livewire::test(ListRecipes::class)
        ->filterTable('sensors', [$xtv->id])
        ->filterTable('film_simulation', [FilmSimulation::ClassicNegative->value])
        ->filterTable('highlight', ['max' => -1])
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$otherSensor, $otherSim, $tooHigh]);
});

it('filters by simulation family, review state and locked fields', function () {
    $neg = Recipe::factory()->create(['film_simulation' => FilmSimulation::NostalgicNeg]);
    $chrome = Recipe::factory()->create(['film_simulation' => FilmSimulation::ClassicChrome, 'locked_fields' => ['highlight']]);
    $review = Recipe::factory()->needsReview()->create();

    Livewire::test(ListRecipes::class)
        ->filterTable('family', ['neg'])
        ->assertCanSeeTableRecords([$neg])
        ->assertCanNotSeeTableRecords([$chrome, $review]);

    Livewire::test(ListRecipes::class)
        ->filterTable('needs_review', true)
        ->assertCanSeeTableRecords([$review])
        ->assertCanNotSeeTableRecords([$neg, $chrome]);

    Livewire::test(ListRecipes::class)
        ->filterTable('locked', true)
        ->assertCanSeeTableRecords([$chrome])
        ->assertCanNotSeeTableRecords([$neg, $review]);
});

it('searches by name, author and notes', function () {
    $byNotes = Recipe::factory()->create(['name' => 'Uno', 'notes' => 'ideal para días nublados']);
    $other = Recipe::factory()->create(['name' => 'Dos', 'notes' => null]);

    Livewire::test(ListRecipes::class)
        ->searchTable('nublados')
        ->assertCanSeeTableRecords([$byNotes])
        ->assertCanNotSeeTableRecords([$other]);
});

it('shows the similar recipes on the view page, filtered by sensor', function () {
    $golden = GoldenRecipes::createAll();
    $xtv = Sensor::where('slug', 'x-trans-v')->firstOrFail();
    $golden['A']->sensors()->attach($xtv);
    $golden['B']->sensors()->attach($xtv);

    Livewire::test(ViewRecipe::class, ['record' => $golden['E']->id])->assertOk();

    // Por defecto filtra por el sensor del usuario (x-trans-v)
    Livewire::test(SimilarRecipes::class, ['record' => $golden['E']])
        ->assertSee('Golden A')
        ->assertSee('3.40')
        ->assertSee('Golden B')
        ->assertDontSee('Golden D')
        ->filterTable('sensor', null)
        ->assertSee('Golden D')
        ->assertSee('5.75')
        ->assertDontSee('Golden E');
});

it('locks the fields edited by hand on imported recipes', function () {
    $recipe = Recipe::factory()->create(['highlight' => -1, 'shadow' => 0, 'name' => 'Kodak Gold']);

    Livewire::test(EditRecipe::class, ['record' => $recipe->id])
        ->fillForm(['highlight' => 1.5, 'name' => 'Mi Kodak Gold'])
        ->call('save')
        ->assertHasNoFormErrors();

    $recipe->refresh();
    expect($recipe->highlight)->toBe('1.5')
        ->and($recipe->locked_fields)->toEqualCanonicalizing(['highlight', 'name']);
});

it('does not lock fields on manual recipes', function () {
    $recipe = Recipe::factory()->manual()->create(['highlight' => -1]);

    Livewire::test(EditRecipe::class, ['record' => $recipe->id])
        ->fillForm(['highlight' => 1])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($recipe->fresh()->locked_fields)->toBe([]);
});

it('validates the setting ranges in the form', function (string $field, mixed $value) {
    $recipe = Recipe::factory()->create();

    Livewire::test(EditRecipe::class, ['record' => $recipe->id])
        ->fillForm([$field => $value])
        ->call('save')
        ->assertHasFormErrors([$field]);
})->with([
    'highlight > 4' => ['highlight', 4.5],
    'highlight fuera de paso' => ['highlight', 0.3],
    'shadow < −2' => ['shadow', -2.5],
    'color decimal' => ['color', 1.5],
    'clarity > 5' => ['clarity', 6],
    'wb_shift_red > 9' => ['wb_shift_red', 10],
]);

it('creates manual recipes', function () {
    Livewire::test(CreateRecipe::class)
        ->fillForm([
            'name' => 'Mi variante',
            'film_simulation' => FilmSimulation::RealaAce->value,
            'grain_roughness' => EffectLevel::Weak->value,
            'highlight' => -0.5,
            'wb_mode' => 'Kelvin',
            'wb_kelvin' => 5300,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $recipe = Recipe::where('name', 'Mi variante')->firstOrFail();
    expect($recipe->source)->toBe(RecipeSource::Manual)
        ->and($recipe->film_simulation)->toBe(FilmSimulation::RealaAce)
        ->and($recipe->wb_kelvin)->toBe(5300)
        ->and($recipe->slug)->toBe('mi-variante');
});

it('requires kelvin when the white balance mode is Kelvin', function () {
    Livewire::test(CreateRecipe::class)
        ->fillForm(['name' => 'X', 'wb_mode' => 'Kelvin', 'wb_kelvin' => null])
        ->call('create')
        ->assertHasFormErrors(['wb_kelvin']);
});

it('summarizes white balance and grain', function () {
    $recipe = Recipe::factory()->make([
        'wb_mode' => 'Auto', 'wb_kelvin' => null, 'wb_shift_red' => 1, 'wb_shift_blue' => -2,
        'grain_roughness' => EffectLevel::Weak, 'grain_size' => 'Small',
    ]);
    $kelvin = Recipe::factory()->make(['wb_mode' => 'Kelvin', 'wb_kelvin' => 5200, 'wb_shift_red' => 0, 'wb_shift_blue' => -4]);

    expect($recipe->whiteBalanceSummary())->toBe('Auto +1R −2B')
        ->and($recipe->grainSummary())->toBe('Weak, Small')
        ->and($kelvin->whiteBalanceSummary())->toBe('5200K 0R −4B');
});
