<?php

use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\RecipeSource;
use App\Filament\Pages\CompareRecipes;
use App\Filament\Resources\RecipeCollections\Pages\CreateRecipeCollection;
use App\Filament\Resources\RecipeCollections\Pages\EditRecipeCollection;
use App\Filament\Resources\RecipeCollections\Pages\ViewRecipeCollection;
use App\Filament\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Resources\Recipes\Pages\ViewRecipe;
use App\Filament\Resources\Sensors\Pages\ListSensors;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\Recipe;
use App\Models\RecipeCollection;
use App\Models\Sensor;
use App\Models\Tag;
use App\Models\User;
use App\Support\RecipeText;
use Database\Seeders\SensorSeeder;
use Livewire\Livewire;
use Tests\Fixtures\GoldenRecipes;

/**
 * Estado interno del repeater simple de slots (en la UI es una lista plana de ids).
 */
function slots(array $ids): array
{
    return array_map(fn (int $id) => ['recipe_id' => $id], $ids);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->seed(SensorSeeder::class);
});

describe('comparador', function () {
    it('compares 3 recipes side by side with their pairwise distances', function () {
        $golden = GoldenRecipes::createAll();
        $ids = [$golden['E']->id, $golden['A']->id, $golden['C']->id];

        Livewire::withQueryParams(['ids' => implode(',', $ids)])
            ->test(CompareRecipes::class)
            ->assertOk()
            ->assertSee(['Golden E', 'Golden A', 'Golden C'])
            ->assertSee(['Golden E ↔ Golden A', 'Golden E ↔ Golden C', 'Golden A ↔ Golden C'])
            ->assertSee(['3.40', '8.05'])
            ->assertCanSeeTableRecords(['film_simulation', 'highlight', 'clarity'])
            ->filterTable('only_differences', true)
            ->assertCanSeeTableRecords(['clarity', 'color'])
            // Las tres son Reala Ace: la simulación no difiere
            ->assertCanNotSeeTableRecords(['film_simulation', 'high_iso_nr']);
    });

    it('opens the comparator from the bulk action with 2 to 4 recipes', function () {
        $recipes = Recipe::factory()->count(3)->create();

        Livewire::test(ListRecipes::class)
            ->callTableBulkAction('compare', $recipes)
            ->assertRedirect(CompareRecipes::urlFor($recipes->sortBy('id')->modelKeys()));
    });

    it('refuses to compare fewer than 2 or more than 4 recipes', function (int $count) {
        $recipes = Recipe::factory()->count($count)->create();

        Livewire::test(ListRecipes::class)
            ->callTableBulkAction('compare', $recipes)
            ->assertNoRedirect()
            ->assertNotified('Selecciona de 2 a 4 recetas para comparar.');
    })->with([1, 5]);

    it('redirects back to the list when the page gets a wrong number of ids', function () {
        Livewire::withQueryParams(['ids' => (string) Recipe::factory()->create()->id])
            ->test(CompareRecipes::class)
            ->assertRedirect('/recipes');
    });
});

describe('duplicar y copiar como texto', function () {
    it('duplicates an imported recipe as a manual one', function () {
        $tag = Tag::factory()->create();
        $original = Recipe::factory()->create([
            'name' => 'Kodak Gold', 'locked_fields' => ['highlight'], 'needs_review' => true, 'raw_json' => ['a' => 1],
        ]);
        $original->sensors()->attach(Sensor::where('slug', 'x-trans-v')->first(), ['index_label' => 'Kodak Gold']);
        $original->tags()->attach($tag);

        Livewire::test(ViewRecipe::class, ['record' => $original->id])
            ->callAction('duplicate')
            ->assertHasNoActionErrors();

        $copy = Recipe::where('name', 'Kodak Gold (copia)')->firstOrFail();
        expect($copy->source)->toBe(RecipeSource::Manual)
            ->and($copy->source_url)->toBeNull()
            ->and($copy->slug)->toBe('kodak-gold-copia')
            ->and($copy->locked_fields)->toBe([])
            ->and($copy->needs_review)->toBeFalse()
            ->and($copy->raw_json)->toBeNull()
            ->and($copy->highlight)->toBe($original->highlight)
            ->and($copy->film_simulation)->toBe($original->film_simulation)
            ->and($copy->sensors->pluck('slug')->all())->toBe(['x-trans-v'])
            ->and($copy->tags->pluck('id')->all())->toBe([$tag->id])
            ->and($copy->notes)->toContain('Variante de «Kodak Gold»')
            ->and($original->fresh()->locked_fields)->toBe(['highlight']);
    });

    it('exports a recipe in the "Key: value" format of fxw_import.py', function () {
        $recipe = GoldenRecipes::make('E');
        $recipe->fill(['iso_raw' => 'Auto up to ISO 6400', 'exposure_compensation' => '+1/3 (typically)']);

        expect(RecipeText::make($recipe))->toBe(<<<'TXT'
            Film Simulation: Reala Ace
            Grain Effect: Weak, Small
            Color Chrome Effect: Strong
            Color Chrome FX Blue: Weak
            White Balance: Auto, +2 Red & -3 Blue
            Dynamic Range: DR200
            Highlight: -1
            Shadow: -0.5
            Color: +3
            Sharpness: -1
            High ISO NR: -4
            Clarity: -3
            ISO: Auto up to ISO 6400
            Exposure Compensation: +1/3 (typically)
            TXT);
    });

    it('exports Kelvin white balance and omits missing settings', function () {
        $text = RecipeText::make(GoldenRecipes::make('I'));

        expect($text)->toContain('White Balance: 5200K, +2 Red & -4 Blue')
            ->not->toContain('Grain Effect')
            ->not->toContain('Sharpness');
    });

    it('offers the copy action on the recipe page', function () {
        $recipe = Recipe::factory()->create();

        Livewire::test(ViewRecipe::class, ['record' => $recipe->id])
            ->assertActionVisible('copyText')
            ->assertActionVisible('duplicate');
    });
});

describe('etiquetas', function () {
    it('tags several recipes at once without removing their tags', function () {
        $existing = Tag::factory()->create(['name' => 'retrato']);
        $night = Tag::factory()->create(['name' => 'noche']);
        $recipes = Recipe::factory()->count(2)->create();
        $recipes[0]->tags()->attach($existing);

        Livewire::test(ListRecipes::class)
            ->callTableBulkAction('tag', $recipes, ['tags' => [$night->id]])
            ->assertHasNoActionErrors();

        expect($recipes[0]->tags()->pluck('name')->sort()->values()->all())->toBe(['noche', 'retrato'])
            ->and($recipes[1]->tags()->pluck('name')->all())->toBe(['noche']);
    });

    it('filters recipes by tag', function () {
        $tag = Tag::factory()->create(['name' => 'nublado']);
        [$tagged, $other] = Recipe::factory()->count(2)->create();
        $tagged->tags()->attach($tag);

        Livewire::test(ListRecipes::class)
            ->filterTable('tags', [$tag->id])
            ->assertCanSeeTableRecords([$tagged])
            ->assertCanNotSeeTableRecords([$other]);
    });

    it('lists tags and sensors', function () {
        Tag::factory()->create(['name' => 'noche']);

        Livewire::test(ListTags::class)->assertOk()->assertSee('noche');
        Livewire::test(ListSensors::class)->assertOk()->assertSee(['X-Trans V', 'X-T30 III']);
        $this->get('/sensors')->assertOk();
        $this->get('/sensors/create')->assertNotFound();
    });
});

describe('colecciones', function () {
    it('creates a collection of 7 recipes in order', function () {
        $recipes = Recipe::factory()->count(7)->create();
        $ids = $recipes->modelKeys();

        Livewire::test(CreateRecipeCollection::class)
            ->fillForm(['name' => 'Verano', 'slots' => slots($ids)])
            ->call('create')
            ->assertHasNoFormErrors();

        $collection = RecipeCollection::where('name', 'Verano')->firstOrFail();
        expect($collection->recipes->pluck('id')->all())->toBe($ids)
            ->and($collection->recipes->pluck('pivot.position')->all())->toBe([1, 2, 3, 4, 5, 6, 7]);

        Livewire::test(ViewRecipeCollection::class, ['record' => $collection->id])
            ->assertOk()
            ->assertSee(['C1', 'C7', $recipes[6]->name, 'Colección completa']);
    });

    it('does not allow more than 7 recipes or repeated ones', function () {
        $recipes = Recipe::factory()->count(8)->create();

        Livewire::test(CreateRecipeCollection::class)
            ->fillForm(['name' => 'Demasiadas', 'slots' => slots($recipes->modelKeys())])
            ->call('create')
            ->assertHasFormErrors(['slots']);

        Livewire::test(CreateRecipeCollection::class)
            ->fillForm(['name' => 'Repetida', 'slots' => slots([$recipes[0]->id, $recipes[0]->id])])
            ->call('create')
            ->assertHasFormErrors();

        expect(RecipeCollection::count())->toBe(0);
    });

    it('reorders the slots without breaking the unique position', function () {
        [$a, $b, $c] = Recipe::factory()->count(3)->create();
        $collection = RecipeCollection::factory()->create();
        $collection->setRecipes([$a->id, $b->id, $c->id]);

        Livewire::test(EditRecipeCollection::class, ['record' => $collection->id])
            ->assertSee([$a->name, $b->name, $c->name])
            ->fillForm(['slots' => slots([$c->id, $a->id])])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($collection->fresh()->recipes->pluck('id')->all())->toBe([$c->id, $a->id]);
    });

    it('adds recipes to a collection from the list, at the end and without duplicates', function () {
        $recipes = Recipe::factory()->count(3)->create();
        $collection = RecipeCollection::factory()->create();
        $collection->setRecipes([$recipes[0]->id]);

        Livewire::test(ListRecipes::class)
            ->callTableBulkAction('addToCollection', $recipes, ['collection_id' => $collection->id])
            ->assertHasNoActionErrors()
            ->assertNotified("Añadidas 2 recetas a «{$collection->name}»");

        $ids = $collection->fresh()->recipes->pluck('id');
        expect($ids->first())->toBe($recipes[0]->id)
            ->and($ids->all())->toEqualCanonicalizing($recipes->modelKeys());
    });

    it('refuses to overflow a collection from the list', function () {
        $collection = RecipeCollection::factory()->create();
        $collection->setRecipes(Recipe::factory()->count(6)->create()->modelKeys());
        $more = Recipe::factory()->count(2)->create();

        Livewire::test(ListRecipes::class)
            ->callTableBulkAction('addToCollection', $more, ['collection_id' => $collection->id])
            ->assertNotified('No se han añadido');

        expect($collection->fresh()->recipes)->toHaveCount(6);
    });
});

it('renders the new pages over HTTP', function () {
    [$a, $b] = Recipe::factory()->count(2)->create(['film_simulation' => FilmSimulation::Eterna, 'grain_roughness' => EffectLevel::Off]);
    $collection = RecipeCollection::factory()->create();
    $collection->setRecipes([$a->id]);

    $this->get(CompareRecipes::urlFor([$a->id, $b->id]))->assertOk()->assertSee('Comparar recetas');
    $this->get('/collections')->assertOk();
    $this->get("/collections/{$collection->id}")->assertOk();
    $this->get("/collections/{$collection->id}/edit")->assertOk();
    $this->get('/tags')->assertOk();
});
