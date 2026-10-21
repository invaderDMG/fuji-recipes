<?php

use App\Enums\FilmSimulation;
use App\Models\Recipe;
use App\Models\Sensor;
use App\Services\RecipeSimilarity;
use App\Services\SimilarityResult;
use Tests\Fixtures\GoldenRecipes;

beforeEach(function () {
    $this->similarity = new RecipeSimilarity;
});

it('matches the golden distances computed with fxw_mine.py', function (string $a, string $b, float $expected) {
    $result = $this->similarity->distance(GoldenRecipes::make($a), GoldenRecipes::make($b));

    expect($result->total)->toEqualWithDelta($expected, 0.001);
})->with([
    'E–A' => ['E', 'A', 3.40],
    'E–B' => ['E', 'B', 13.15],
    'E–C' => ['E', 'C', 8.05],
    'E–D' => ['E', 'D', 5.75],
    'H–F' => ['H', 'F', 3.00],
    'G–F' => ['G', 'F', 5.90],
    'H–G' => ['H', 'G', 5.90],
    'G–G' => ['G', 'G', 0.00],
    'I–J' => ['I', 'J', 9.45],
    'F–I' => ['F', 'I', 19.60],
]);

it('is symmetric', function () {
    $ids = array_keys(GoldenRecipes::rows());

    foreach ($ids as $a) {
        foreach ($ids as $b) {
            expect($this->similarity->distance(GoldenRecipes::make($a), GoldenRecipes::make($b))->total)
                ->toEqualWithDelta($this->similarity->distance(GoldenRecipes::make($b), GoldenRecipes::make($a))->total, 1e-9);
        }
    }
});

it('breaks down E–C with the grain size quirk', function () {
    $parts = $this->similarity->distance(GoldenRecipes::make('E'), GoldenRecipes::make('C'))->parts;

    // C tiene grano Off: su tamaño cuenta como ausente → 0.2 × 0.5
    expect($parts['clarity'])->toEqualWithDelta(1.2, 0.001)
        ->and($parts['grain_size'])->toEqualWithDelta(0.1, 0.001);
});

it('reports differing fields and top contributors', function () {
    $result = $this->similarity->distance(GoldenRecipes::make('E'), GoldenRecipes::make('B'));

    expect($result->differingFields())->toContain('film_simulation', 'wb_mode', 'wb_shift_red')
        ->not->toContain('high_iso_nr', 'color')
        ->and(array_keys($result->topContributors()))->toBe(['film_simulation', 'wb_mode', 'highlight'])
        ->and($result->topContributors())->toHaveCount(3);
});

it('compares unmapped simulations by their original text', function () {
    $a = Recipe::factory()->needsReview()->make(['film_simulation_raw' => 'Any (See Below)']);
    $b = Recipe::factory()->needsReview()->make(['film_simulation_raw' => 'any (see below)']);
    $c = Recipe::factory()->needsReview()->make(['film_simulation_raw' => 'B&W Toning: 0']);

    expect($this->similarity->distance($a, $b)->parts['film_simulation'])->toBe(0.0)
        ->and($this->similarity->distance($a, $c)->parts['film_simulation'])->toBe(6.0)
        ->and($this->similarity->distance($a, GoldenRecipes::make('A'))->parts['film_simulation'])->toBe(6.0);
});

it('reads the weights from config', function () {
    config(['fuji.similarity.weights.clarity' => 1.0]);

    $parts = (new RecipeSimilarity)->distance(GoldenRecipes::make('E'), GoldenRecipes::make('C'))->parts;

    expect($parts['clarity'])->toEqualWithDelta(3.0, 0.001);
});

it('returns the nearest recipes sorted, without the recipe itself', function () {
    $golden = GoldenRecipes::createAll();

    $nearest = $this->similarity->nearest($golden['E'], 3);

    expect($nearest)->toHaveCount(3)
        ->and($nearest->every(fn ($r) => $r instanceof SimilarityResult))->toBeTrue()
        ->and($nearest->map(fn ($r) => $r->recipe->name)->all())->toBe(['Golden A', 'Golden D', 'Golden C'])
        ->and($nearest->pluck('total')->all())->toEqualWithDelta([3.40, 5.75, 8.05], 0.001)
        ->and($this->similarity->nearest($golden['E'], 100)->pluck('recipe.id'))->not->toContain($golden['E']->id)
        ->and($this->similarity->nearest($golden['E'], 100))->toHaveCount(9);
});

it('filters nearest by sensor and same simulation', function () {
    $golden = GoldenRecipes::createAll();
    $sensor = Sensor::factory()->create(['slug' => 'x-trans-v']);
    $golden['B']->sensors()->attach($sensor);
    $golden['C']->sensors()->attach($sensor);

    expect($this->similarity->nearest($golden['E'], sensor: 'x-trans-v')->map(fn ($r) => $r->recipe->name)->all())
        ->toBe(['Golden C', 'Golden B'])
        ->and($this->similarity->nearest($golden['E'], sameSimulation: true)->map(fn ($r) => $r->recipe->film_simulation)->unique()->all())
        ->toBe([FilmSimulation::RealaAce])
        ->and($this->similarity->nearest($golden['E'], sameSimulation: true))->toHaveCount(2);
});
