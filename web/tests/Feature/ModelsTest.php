<?php

use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\RecipeSource;
use App\Models\Recipe;
use App\Models\RecipeCollection;
use App\Models\Sensor;
use App\Models\Tag;
use Database\Seeders\SensorSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

it('generates recipes within the valid ranges', function () {
    $recipes = Recipe::factory()->count(50)->create();

    foreach ($recipes->map->fresh() as $r) {
        expect((float) $r->highlight)->toBeBetween(-2, 4)
            ->and(fmod((float) $r->highlight * 2, 1))->toEqual(0)
            ->and((float) $r->shadow)->toBeBetween(-2, 4)
            ->and((float) $r->sharpness)->toBeBetween(-4, 4)
            ->and((float) $r->high_iso_nr)->toBeBetween(-4, 4)
            ->and((float) $r->clarity)->toBeBetween(-5, 5)
            ->and($r->wb_shift_red)->toBeBetween(-9, 9)
            ->and($r->wb_shift_blue)->toBeBetween(-9, 9)
            ->and($r->film_simulation)->toBeInstanceOf(FilmSimulation::class)
            ->and($r->source)->toBe(RecipeSource::Fxw)
            ->and($r->locked_fields)->toBe([])
            ->and($r->needs_review)->toBeFalse();

        if ($r->color !== null) {
            expect((float) $r->color)->toBeBetween(-4, 4);
        }
        if ($r->wb_kelvin !== null) {
            expect($r->wb_kelvin)->toBeBetween(2500, 10000);
        }
        if ($r->grain_roughness === EffectLevel::Off) {
            expect($r->grain_size)->toBeNull();
        }
    }
});

it('creates mine, manual and needs-review recipes', function () {
    $mine = Recipe::factory()->mine()->create(['name' => 'Mi Gold']);
    $manual = Recipe::factory()->manual()->create();
    $review = Recipe::factory()->needsReview()->create();

    expect($mine->source)->toBe(RecipeSource::Mine)
        ->and($mine->source_url)->toBe('mine:Mi Gold')
        ->and($manual->source)->toBe(RecipeSource::Manual)
        ->and($manual->source_url)->toBeNull()
        ->and($review->fresh()->film_simulation)->toBeNull()
        ->and($review->fresh()->needs_review)->toBeTrue();
});

it('generates unique slugs from the name', function () {
    $a = Recipe::factory()->create(['name' => 'CineStill 800T']);
    $b = Recipe::factory()->create(['name' => 'CineStill 800T']);

    expect($a->slug)->toBe('cinestill-800t')
        ->and($b->slug)->toBe('cinestill-800t-2');
});

it('enforces the natural key', function () {
    Recipe::factory()->create(['source_url' => 'https://fujixweekly.com/x/', 'block_index' => 1]);
    Recipe::factory()->create(['source_url' => 'https://fujixweekly.com/x/', 'block_index' => 1]);
})->throws(UniqueConstraintViolationException::class);

it('stores decimals with one place and json as arrays', function () {
    $r = Recipe::factory()->create([
        'highlight' => -1.5,
        'locked_fields' => ['highlight'],
        'raw_json' => ['Highlight' => '-1.5'],
    ])->fresh();

    expect($r->highlight)->toBe('-1.5')
        ->and($r->locked_fields)->toBe(['highlight'])
        ->and($r->isLocked('highlight'))->toBeTrue()
        ->and($r->isLocked('shadow'))->toBeFalse()
        ->and($r->raw_json)->toBe(['Highlight' => '-1.5']);
});

it('relates recipes with sensors, tags and collections', function () {
    $recipe = Recipe::factory()->create();
    $sensor = Sensor::factory()->create();
    $tag = Tag::factory()->create(['name' => 'nublado']);
    $collection = RecipeCollection::factory()->create();
    $other = Recipe::factory()->create();

    $recipe->sensors()->attach($sensor, ['index_label' => 'Kodak Gold 200']);
    $recipe->tags()->attach($tag);
    $collection->recipes()->attach([$recipe->id => ['position' => 2], $other->id => ['position' => 1]]);

    expect($recipe->sensors->first()->pivot->index_label)->toBe('Kodak Gold 200')
        ->and($tag->recipes->pluck('id')->all())->toBe([$recipe->id])
        ->and($collection->recipes->pluck('id')->all())->toBe([$other->id, $recipe->id])
        ->and($recipe->collections->first()->pivot->position)->toBe(2);
});

it('refuses two recipes in the same collection slot', function () {
    $collection = RecipeCollection::factory()->create();
    [$a, $b] = Recipe::factory()->count(2)->create();

    $collection->recipes()->attach($a, ['position' => 1]);
    $collection->recipes()->attach($b, ['position' => 1]);
})->throws(UniqueConstraintViolationException::class);

it('seeds the 9 source sensors idempotently', function () {
    $this->seed(SensorSeeder::class);
    $this->seed(SensorSeeder::class);

    expect(Sensor::pluck('slug')->all())->toEqualCanonicalizing([
        'x-trans-v', 'x-trans-iv', 'x-trans-iii', 'x-trans-ii', 'x-trans-i',
        'bayer', 'exr-cmos', 'gfx', 'full-spectrum',
    ]);
});
