<?php

use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Resources\Recipes\Pages\ViewRecipe;
use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeSimilarity;
use Database\Seeders\SensorSeeder;
use Filament\Tables\Filters\TrashedFilter;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->seed(SensorSeeder::class);
});

it('soft deletes recipes from the list and can restore them', function () {
    [$a, $b, $keep] = Recipe::factory()->count(3)->create();

    Livewire::withQueryParams(['tab' => 'all'])->test(ListRecipes::class)
        ->callTableBulkAction('delete', [$a, $b])
        ->assertCanNotSeeTableRecords([$a, $b])
        ->assertCanSeeTableRecords([$keep])
        ->filterTable(TrashedFilter::class, false)
        ->assertCanSeeTableRecords([$a, $b])
        ->assertCanNotSeeTableRecords([$keep])
        ->callTableBulkAction('restore', [$a]);

    expect(Recipe::withTrashed()->count())->toBe(3)
        ->and($a->fresh()->trashed())->toBeFalse()
        ->and($b->fresh()->trashed())->toBeTrue();
});

it('shows a deleted recipe with a notice and a restore action', function () {
    $recipe = Recipe::factory()->create();
    $recipe->delete();

    $this->get("/recipes/{$recipe->id}")->assertOk()->assertSee('La importación no la vuelve a crear');

    Livewire::test(ViewRecipe::class, ['record' => $recipe->id])
        ->assertActionVisible('restore')
        ->assertActionHidden('edit')
        ->assertActionHidden('restoreSource')
        ->callAction('restore');

    expect($recipe->fresh()->trashed())->toBeFalse();
});

it('keeps deleted recipes out of similar results and the API', function () {
    [$target, $deleted, $other] = Recipe::factory()->count(3)->create();
    $deleted->delete();

    expect(app(RecipeSimilarity::class)->nearest($target)->map(fn ($r) => $r->recipe->id)->all())
        ->toBe([$other->id]);

    $this->getJson('/api/recipes')->assertJsonPath('meta.total', 2);
    $this->getJson("/api/recipes/{$deleted->id}")->assertNotFound();
});

it('does not insert again through the API a recipe that was deleted', function () {
    $recipe = Recipe::factory()->manual()->create(['source_url' => 'mine:Mi Gold']);
    $recipe->delete();

    $this->postJson('/api/recipes', ['name' => 'Mi Gold', 'source_url' => 'mine:Mi Gold'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.source_url.0', "Esa receta está borrada en la app (#{$recipe->id}) y no se vuelve a insertar.");

    expect(Recipe::withTrashed()->count())->toBe(1);
});

it('keeps slugs unique including deleted recipes', function () {
    Recipe::factory()->create(['name' => 'Kodak Gold'])->delete();

    expect(Recipe::factory()->create(['name' => 'Kodak Gold'])->slug)->toBe('kodak-gold-2');
});
