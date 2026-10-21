<?php

use App\Filament\Pages\CompareRecipes;
use App\Filament\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Resources\Recipes\Pages\ViewRecipe;
use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\SensorSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->seed(SensorSeeder::class);
});

it('rates and comments a recipe from its page without locking fields', function () {
    $recipe = Recipe::factory()->create();

    Livewire::test(ViewRecipe::class, ['record' => $recipe->id])
        ->callAction('rate', ['rating' => 8, 'notes' => 'Muy buena en días nublados'])
        ->assertHasNoActionErrors()
        ->assertNotified('Valoración guardada');

    $recipe->refresh();
    expect($recipe->rating)->toBe(8)
        ->and($recipe->notes)->toBe('Muy buena en días nublados')
        ->and($recipe->locked_fields)->toBe([]);

    $this->get("/recipes/{$recipe->id}")->assertSee(['8/10', 'Comentarios', 'Muy buena en días nublados']);
});

it('accepts 0 as a rating and rejects values above 10', function () {
    $recipe = Recipe::factory()->create();

    Livewire::test(EditRecipe::class, ['record' => $recipe->id])
        ->fillForm(['rating' => 0])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($recipe->fresh()->rating)->toBe(0);
    $this->get("/recipes/{$recipe->id}")->assertSee('0/10');

    Livewire::test(EditRecipe::class, ['record' => $recipe->id])
        ->fillForm(['rating' => 11])
        ->call('save')
        ->assertHasFormErrors(['rating']);

    $this->postJson('/api/recipes', ['name' => 'X', 'rating' => 11])->assertJsonValidationErrors(['rating']);
    $this->postJson('/api/recipes', ['name' => 'Y', 'rating' => 0])->assertCreated()->assertJsonPath('data.personal.rating', 0);
});

it('filters and sorts by rating', function () {
    $low = Recipe::factory()->create(['rating' => 3]);
    $zero = Recipe::factory()->create(['rating' => 0]);
    $high = Recipe::factory()->create(['rating' => 9]);
    $none = Recipe::factory()->create(['rating' => null]);

    Livewire::withQueryParams(['tab' => 'all'])
        ->test(ListRecipes::class)
        ->filterTable('rating', ['min' => 7])
        ->assertCanSeeTableRecords([$high])
        ->assertCanNotSeeTableRecords([$low, $zero, $none])
        ->filterTable('rating', ['max' => 3])
        ->assertCanSeeTableRecords([$low, $zero])
        ->assertCanNotSeeTableRecords([$high, $none]);

    $this->getJson('/api/recipes?rating_min=7')->assertJsonPath('meta.total', 1);
    $this->getJson('/api/recipes?sort=-rating&per_page=1')->assertJsonPath('data.0.personal.rating', 9);
});

it('shows the rating in the comparator', function () {
    [$a, $b] = Recipe::factory()->count(2)->sequence(['rating' => 7], ['rating' => null])->create();

    Livewire::withQueryParams(['ids' => "{$a->id},{$b->id}"])
        ->test(CompareRecipes::class)
        ->assertSee(['Valoración', '7/10']);
});
