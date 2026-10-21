<?php

use App\Enums\PhotoSource;
use App\Filament\Resources\Recipes\Pages\ViewRecipe;
use App\Filament\Resources\Recipes\RelationManagers\MyPhotosRelationManager;
use App\Models\Recipe;
use App\Models\RecipePhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Storage::fake(RecipePhoto::DISK);
});

it('shows the sample photos on the recipe page', function () {
    $recipe = Recipe::factory()->create();
    RecipePhoto::factory()->create([
        'recipe_id' => $recipe->id,
        'url' => 'https://i0.wp.com/fujixweekly.com/wp-content/uploads/2024/05/sample.jpg',
        'caption' => 'Coast Blue – San Simeon, CA',
    ]);

    $this->get("/recipes/{$recipe->id}")
        ->assertOk()
        ->assertSee('Fotos de muestra')
        ->assertSee('sample.jpg?w=480', false)
        ->assertSee('Coast Blue – San Simeon, CA');
});

it('uploads own photos from the recipe page and deletes their files', function () {
    $recipe = Recipe::factory()->create();

    Livewire::test(MyPhotosRelationManager::class, ['ownerRecord' => $recipe, 'pageClass' => ViewRecipe::class])
        ->assertOk()
        ->callTableAction('create', data: [
            'path' => UploadedFile::fake()->image('mi-foto.jpg', 3000, 2000),
            'caption' => 'Primera prueba',
        ])
        ->assertHasNoTableActionErrors();

    $photo = $recipe->myPhotos()->firstOrFail();
    expect($photo->source)->toBe(PhotoSource::Mine)
        ->and($photo->caption)->toBe('Primera prueba')
        ->and($photo->position)->toBe(1)
        ->and($photo->path)->toStartWith('photos/');
    Storage::disk(RecipePhoto::DISK)->assertExists($photo->path);

    Livewire::test(MyPhotosRelationManager::class, ['ownerRecord' => $recipe, 'pageClass' => ViewRecipe::class])
        ->assertSee('Primera prueba')
        ->callTableAction('delete', $photo);

    Storage::disk(RecipePhoto::DISK)->assertMissing($photo->path);
    expect(RecipePhoto::count())->toBe(0);
});

it('separates sample and own photos', function () {
    $recipe = Recipe::factory()->create();
    RecipePhoto::factory()->create(['recipe_id' => $recipe->id]);
    RecipePhoto::factory()->mine()->create(['recipe_id' => $recipe->id]);

    expect($recipe->samplePhotos)->toHaveCount(1)
        ->and($recipe->myPhotos)->toHaveCount(1)
        ->and($recipe->myPhotos->first()->src())->toContain('/storage/photos/');
});

it('includes the photos in the API', function () {
    $recipe = Recipe::factory()->create();
    RecipePhoto::factory()->create(['recipe_id' => $recipe->id, 'caption' => 'Muestra']);

    $this->getJson("/api/recipes/{$recipe->id}")
        ->assertJsonPath('data.photos.0.source', 'sample')
        ->assertJsonPath('data.photos.0.caption', 'Muestra');
});
