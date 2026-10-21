<?php

use App\Filament\Pages\CompareRecipes;
use App\Filament\Pages\MyCameraSettings;
use App\Filament\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Resources\Recipes\Pages\ViewRecipe;
use App\Filament\Resources\Recipes\Widgets\SimilarRecipes;
use App\Models\Recipe;
use App\Models\Sensor;
use App\Models\User;
use App\Support\MyCamera;
use Database\Seeders\LocalUserSeeder;
use Database\Seeders\SensorSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([SensorSeeder::class, LocalUserSeeder::class]);
    $this->user = User::firstWhere('email', config('fuji.local_user.email'));
    $this->actingAs($this->user);

    $this->xtv = Sensor::where('slug', 'x-trans-v')->first();
    $this->xt4 = Sensor::where('slug', 'x-trans-iv')->first();
    $this->forV = Recipe::factory()->create(['name' => 'Para la V']);
    $this->forV->sensors()->attach($this->xtv);
    $this->forIV = Recipe::factory()->create(['name' => 'Para la IV']);
    $this->forIV->sensors()->attach($this->xt4);
    $this->both = Recipe::factory()->create(['name' => 'Para las dos']);
    $this->both->sensors()->attach([$this->xtv->id, $this->xt4->id]);
});

it('starts with the default camera from the config', function () {
    expect(MyCamera::camera())->toBe('X-T30 III')
        ->and(MyCamera::sensorSlug())->toBe('x-trans-v')
        ->and(MyCamera::label())->toBe('X-T30 III (X-Trans V)');
});

it('shows compatible recipes by default, with tabs for all and incompatible', function () {
    Livewire::test(ListRecipes::class)
        ->assertSee('Compatibles con mi X-T30 III')
        ->assertCanSeeTableRecords([$this->forV, $this->both])
        ->assertCanNotSeeTableRecords([$this->forIV])
        ->set('activeTab', 'incompatible')
        ->assertCanSeeTableRecords([$this->forIV])
        ->assertCanNotSeeTableRecords([$this->forV, $this->both])
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$this->forV, $this->forIV, $this->both]);
});

it('changes the camera and with it the compatible recipes', function () {
    Livewire::test(MyCameraSettings::class)
        ->assertSchemaStateSet(['camera' => 'x-trans-v|X-T30 III'])
        ->fillForm(['camera' => 'x-trans-iv|X-T4'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Cámara guardada: X-T4 (X-Trans IV)');

    expect($this->user->fresh()->only('camera', 'camera_sensor'))->toBe(['camera' => 'X-T4', 'camera_sensor' => 'x-trans-iv']);

    Livewire::test(ListRecipes::class)
        ->assertSee('Compatibles con mi X-T4')
        ->assertCanSeeTableRecords([$this->forIV, $this->both])
        ->assertCanNotSeeTableRecords([$this->forV]);
});

it('offers the cameras of every sensor grouped by sensor', function () {
    expect(MyCamera::options()['X-Trans V'])->toHaveKey('x-trans-v|X-T30 III')
        ->and(MyCamera::options()['X-Trans IV'])->toHaveKey('x-trans-iv|X-T4');

    $this->get('/my-camera')->assertOk()->assertSee('Mi cámara');
});

it('tells on the recipe page whether it is compatible', function () {
    Livewire::test(ViewRecipe::class, ['record' => $this->forV->id])->assertSee('Compatible con tu X-T30 III (X-Trans V)');
    Livewire::test(ViewRecipe::class, ['record' => $this->forIV->id])->assertSee('No figura para tu X-T30 III');
});

it('uses my camera for similar recipes, new recipes, the comparator and the API', function () {
    Livewire::test(SimilarRecipes::class, ['record' => $this->both])
        ->assertSee('Para la V')
        ->assertDontSee('Para la IV');

    Livewire::test(CreateRecipe::class)->assertSchemaStateSet(['sensors' => [$this->xtv->id]]);

    Livewire::withQueryParams(['ids' => "{$this->forV->id},{$this->forIV->id}"])
        ->test(CompareRecipes::class)
        ->assertSee(['Mi X-T30 III', '✓ Compatible', 'No figura']);

    $this->getJson('/api/recipes?compatible=true')->assertJsonPath('meta.total', 2);
    $this->getJson('/api/recipes?compatible=false')->assertJsonPath('meta.total', 1);
    $this->getJson("/api/recipes/{$this->forIV->id}")->assertJsonPath('data.compatible_with_my_camera', false);
});

it('uses the local user camera in the API, without a session', function () {
    $this->user->update(['camera' => 'X-T4', 'camera_sensor' => 'x-trans-iv']);
    auth()->logout();

    $this->getJson('/api/recipes?compatible=true')->assertJsonPath('meta.total', 2);
    $this->getJson("/api/recipes/{$this->forIV->id}")->assertJsonPath('data.compatible_with_my_camera', true);
});
