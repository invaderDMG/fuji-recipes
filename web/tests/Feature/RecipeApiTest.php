<?php

use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\RecipeSource;
use App\Models\Recipe;
use App\Models\Sensor;
use App\Models\Tag;
use Database\Seeders\SensorSeeder;
use Tests\Fixtures\GoldenRecipes;

beforeEach(function () {
    $this->seed(SensorSeeder::class);
});

describe('GET /api/recipes', function () {
    it('lists recipes paginated, with sensors and tags', function () {
        $recipe = Recipe::factory()->create(['name' => 'Kodak Gold', 'highlight' => -1.5]);
        $recipe->sensors()->attach(Sensor::where('slug', 'x-trans-v')->first());
        $recipe->tags()->attach(Tag::factory()->create(['name' => 'verano']));

        $this->getJson('/api/recipes')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Kodak Gold')
            ->assertJsonPath('data.0.settings.highlight', -1.5)
            ->assertJsonPath('data.0.sensors', ['x-trans-v'])
            ->assertJsonPath('data.0.tags', ['verano'])
            ->assertJsonPath('data.0.links.ui', url("/recipes/{$recipe->id}"));
    });

    it('applies the same filters as the UI', function () {
        $xtv = Sensor::where('slug', 'x-trans-v')->first();
        $match = Recipe::factory()->create(['film_simulation' => FilmSimulation::ClassicNegative, 'highlight' => -1.5, 'is_favorite' => true]);
        $otherSensor = Recipe::factory()->create(['film_simulation' => FilmSimulation::ClassicNegative, 'highlight' => -2]);
        $otherSim = Recipe::factory()->create(['film_simulation' => FilmSimulation::ClassicChrome, 'highlight' => -2]);
        $tooHigh = Recipe::factory()->create(['film_simulation' => FilmSimulation::ClassicNegative, 'highlight' => 0]);
        foreach ([$match, $otherSim, $tooHigh] as $r) {
            $r->sensors()->attach($xtv);
        }

        $ids = fn (string $query) => collect($this->getJson("/api/recipes?{$query}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        expect($ids('sensor=x-trans-v&simulation=Classic%20Negative&highlight_max=-1'))->toBe([$match->id])
            ->and($ids('sensor[]=x-trans-v&family[]=neg'))->toBe(collect([$match->id, $tooHigh->id])->sort()->values()->all())
            ->and($ids('favorite=true'))->toBe([$match->id])
            ->and($ids('favorite=0'))->not->toContain($match->id)
            ->and($ids('highlight_min=-2&highlight_max=-2'))->toBe(collect([$otherSensor->id, $otherSim->id])->sort()->values()->all());
    });

    it('filters by review state, locks, tags and search', function () {
        $review = Recipe::factory()->needsReview()->create(['name' => 'Rara']);
        $locked = Recipe::factory()->create(['locked_fields' => ['highlight'], 'notes' => 'ideal nublado']);
        $tagged = Recipe::factory()->create();
        $tagged->tags()->attach(Tag::factory()->create(['name' => 'noche']));

        $this->getJson('/api/recipes?needs_review=1')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $review->id);
        $this->getJson('/api/recipes?locked=true')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $locked->id);
        $this->getJson('/api/recipes?tag=noche')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $tagged->id);
        $this->getJson('/api/recipes?q=nublado')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $locked->id);
    });

    it('sorts and paginates', function () {
        Recipe::factory()->create(['name' => 'B']);
        Recipe::factory()->create(['name' => 'A']);
        Recipe::factory()->create(['name' => 'C']);

        $this->getJson('/api/recipes?sort=name&per_page=2')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.*.name', ['A', 'B']);
    });

    it('rejects invalid filters', function () {
        $this->getJson('/api/recipes?simulation=Kodachrome&sensor=x-trans-x&per_page=500&sort=id')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['simulation.0', 'sensor.0', 'per_page', 'sort']);
    });
});

describe('GET /api/recipes/{recipe}', function () {
    it('shows a recipe with its text export', function () {
        $recipe = GoldenRecipes::make('E');
        $recipe->save();

        $this->getJson("/api/recipes/{$recipe->id}")
            ->assertOk()
            ->assertJsonPath('data.settings.film_simulation', 'Reala Ace')
            ->assertJsonPath('data.settings.film_simulation_family', 'proneg')
            ->assertJsonPath('data.settings.white_balance.summary', 'Auto +2R −3B')
            ->assertJsonPath('data.settings.grain_roughness', 'Weak')
            ->assertJson(fn ($json) => $json->where('data.as_text', fn (string $text) => str_contains($text, 'Film Simulation: Reala Ace'))->etc());
    });

    it('returns a JSON 404 for unknown recipes', function () {
        $this->get('/api/recipes/999')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    });
});

describe('GET /api/recipes/{recipe}/similar', function () {
    it('returns the nearest recipes with the golden distances', function () {
        $golden = GoldenRecipes::createAll();

        $this->getJson("/api/recipes/{$golden['E']->id}/similar?n=3")
            ->assertOk()
            ->assertJsonPath('recipe.id', $golden['E']->id)
            ->assertJsonPath('data.*.recipe.name', ['Golden A', 'Golden D', 'Golden C'])
            ->assertJsonPath('data.0.distance', 3.4)
            ->assertJsonPath('data.2.breakdown.clarity', 1.2)
            ->assertJsonPath('data.2.breakdown.grain_size', 0.1);
    });

    it('filters by sensor and same simulation', function () {
        $golden = GoldenRecipes::createAll();
        $golden['B']->sensors()->attach(Sensor::where('slug', 'x-trans-v')->first());

        $this->getJson("/api/recipes/{$golden['E']->id}/similar?sensor=x-trans-v")
            ->assertJsonPath('data.*.recipe.name', ['Golden B']);
        $this->getJson("/api/recipes/{$golden['E']->id}/similar?same_sim=true")
            ->assertJsonPath('data.*.recipe.name', ['Golden A', 'Golden C']);
    });

    it('validates n', function () {
        $recipe = Recipe::factory()->create();

        $this->getJson("/api/recipes/{$recipe->id}/similar?n=0")->assertJsonValidationErrors(['n']);
    });
});

describe('POST /api/recipes', function () {
    it('creates an own recipe and returns 201', function () {
        $response = $this->postJson('/api/recipes', [
            'name' => 'Mi Gold',
            'author' => 'Yo',
            'film_simulation' => 'Classic Negative',
            'grain_roughness' => 'Weak',
            'grain_size' => 'Small',
            'wb_mode' => 'Kelvin',
            'wb_kelvin' => 5200,
            'wb_shift_red' => 2,
            'wb_shift_blue' => -4,
            'highlight' => -1.5,
            'color' => 3,
            'sensors' => ['x-trans-v'],
            'tags' => ['verano', 'nueva'],
            'raw_json' => ['film simulation' => 'Classic Negative'],
        ]);

        $recipe = Recipe::where('name', 'Mi Gold')->firstOrFail();
        $response->assertCreated()
            ->assertHeader('Location', route('api.recipes.show', $recipe))
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.settings.white_balance.summary', '5200K +2R −4B')
            ->assertJsonPath('data.sensors', ['x-trans-v']);

        expect($recipe->source)->toBe(RecipeSource::Manual)
            ->and($recipe->film_simulation)->toBe(FilmSimulation::ClassicNegative)
            ->and($recipe->grain_roughness)->toBe(EffectLevel::Weak)
            ->and($recipe->tags->pluck('name')->sort()->values()->all())->toBe(['nueva', 'verano'])
            ->and($recipe->raw_json)->toBe(['film simulation' => 'Classic Negative']);
    });

    it('ignores fields that are not in the request rules', function () {
        $this->postJson('/api/recipes', [
            'name' => 'Intento',
            'source' => 'fxw',
            'locked_fields' => ['highlight'],
            'needs_review' => true,
            'id' => 9999,
        ])->assertCreated();

        $recipe = Recipe::where('name', 'Intento')->firstOrFail();
        expect($recipe->source)->toBe(RecipeSource::Manual)
            ->and($recipe->id)->not->toBe(9999)
            ->and($recipe->locked_fields)->toBe([])
            ->and($recipe->needs_review)->toBeFalse();
    });

    it('validates names, enums and ranges', function (array $payload, array $errors) {
        $this->postJson('/api/recipes', ['name' => 'X', ...$payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errors);

        expect(Recipe::count())->toBe(0);
    })->with([
        'sin nombre' => [['name' => ''], ['name']],
        'simulación desconocida' => [['film_simulation' => 'Kodachrome'], ['film_simulation']],
        'highlight fuera de rango' => [['highlight' => 5], ['highlight']],
        'highlight fuera de paso' => [['highlight' => 0.3], ['highlight']],
        'clarity > 5' => [['clarity' => 6], ['clarity']],
        'kelvin obligatorio' => [['wb_mode' => 'Kelvin'], ['wb_kelvin']],
        'modo de WB desconocido' => [['wb_mode' => 'Sunset'], ['wb_mode']],
        'sensor desconocido' => [['sensors' => ['x-trans-x']], ['sensors.0']],
    ]);

    it('answers validation errors in JSON even without an Accept header', function () {
        $this->post('/api/recipes', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('refuses a duplicated source_url', function () {
        Recipe::factory()->create(['source_url' => 'mine:Mi Gold', 'block_index' => 0]);

        $this->postJson('/api/recipes', ['name' => 'Mi Gold', 'source_url' => 'mine:Mi Gold'])
            ->assertJsonValidationErrors(['source_url']);
    });
});

it('only accepts requests from localhost', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.20'])
        ->getJson('/api/recipes')
        ->assertForbidden();

    $this->withServerVariables(['REMOTE_ADDR' => '::1'])
        ->getJson('/api/recipes')
        ->assertOk();
});
