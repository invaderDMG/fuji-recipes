<?php

use App\Enums\EffectLevel;
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

    $this->perSensor = Recipe::factory()->create([
        'name' => 'Classic Color',
        'color_chrome_fx_blue' => EffectLevel::Strong,
        'needs_review' => true,
        'review_issues' => ['color_chrome_fx_blue' => [
            'reason' => 'ambiguo', 'raw' => 'Strong (X-Trans IV), Weak (X-Trans V)', 'suggestion' => 'Weak',
        ]],
    ]);
    $this->noSuggestion = Recipe::factory()->create([
        'name' => 'Fujicolor C200',
        'needs_review' => true,
        'review_issues' => ['dynamic_range' => [
            'reason' => 'ambiguo', 'raw' => 'DR400 (DR200 in low contrast situations)', 'suggestion' => null,
        ]],
    ]);
    $this->ok = Recipe::factory()->create();
});

it('lists the pending recipes in their tab with the reason and the proposal', function () {
    Livewire::withQueryParams(['tab' => 'review'])
        ->test(ListRecipes::class)
        ->assertSee('Por revisar')
        ->assertCanSeeTableRecords([$this->perSensor, $this->noSuggestion])
        ->assertCanNotSeeTableRecords([$this->ok])
        ->assertSee('FX Blue (ambiguo): «Strong (X-Trans IV), Weak (X-Trans V)» → propuesta Weak')
        ->assertTableActionVisible('acceptSuggestions', $this->perSensor)
        ->assertTableActionHidden('acceptSuggestions', $this->noSuggestion)
        ->callTableAction('acceptSuggestions', $this->perSensor)
        ->assertCanNotSeeTableRecords([$this->perSensor]);

    expect($this->perSensor->fresh()->color_chrome_fx_blue)->toBe(EffectLevel::Weak);
});

it('marks several recipes as reviewed at once', function () {
    Livewire::withQueryParams(['tab' => 'review'])
        ->test(ListRecipes::class)
        ->callTableBulkAction('markReviewed', [$this->perSensor, $this->noSuggestion]);

    expect(Recipe::where('needs_review', true)->count())->toBe(0)
        ->and($this->perSensor->fresh()->color_chrome_fx_blue)->toBe(EffectLevel::Strong)
        ->and($this->perSensor->fresh()->locked_fields)->toBe(['color_chrome_fx_blue']);
});

it('shows the review on the recipe page and accepts the proposal from there', function () {
    Livewire::test(ViewRecipe::class, ['record' => $this->perSensor->id])
        ->assertSee(['Revisión pendiente', 'Strong (X-Trans IV), Weak (X-Trans V)', 'Propuesta para tu cámara'])
        ->callAction('acceptSuggestions')
        ->assertNotified('Propuesta aceptada');

    expect($this->perSensor->fresh()->needs_review)->toBeFalse();

    Livewire::test(ViewRecipe::class, ['record' => $this->ok->id])
        ->assertDontSee('Revisión pendiente')
        ->assertActionHidden('markReviewed');
});

it('resolves an issue when the field is corrected by hand', function () {
    Livewire::test(EditRecipe::class, ['record' => $this->perSensor->id])
        ->fillForm(['color_chrome_fx_blue' => EffectLevel::Off->value])
        ->call('save')
        ->assertHasNoFormErrors();

    $recipe = $this->perSensor->fresh();
    expect($recipe->color_chrome_fx_blue)->toBe(EffectLevel::Off)
        ->and($recipe->needs_review)->toBeFalse()
        ->and($recipe->review_issues)->toBeNull()
        ->and($recipe->locked_fields)->toContain('color_chrome_fx_blue');
});

it('marks as reviewed when needs_review is switched off in the form', function () {
    Livewire::test(EditRecipe::class, ['record' => $this->noSuggestion->id])
        ->fillForm(['needs_review' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->noSuggestion->fresh()->locked_fields)->toBe(['dynamic_range'])
        ->and($this->noSuggestion->fresh()->needs_review)->toBeFalse();
});
