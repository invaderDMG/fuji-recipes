<?php

namespace Database\Factories;

use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\GrainSize;
use App\Enums\RecipeSource;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Genera recetas con ajustes dentro de los rangos válidos de la sección 5.
 *
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    public function definition(): array
    {
        $name = ucwords($this->faker->unique()->words(2, true));
        $simulation = $this->faker->randomElement(FilmSimulation::cases());
        $grain = $this->faker->randomElement(EffectLevel::cases());
        $wbMode = $this->faker->randomElement(['Auto', 'Auto White Priority', 'Daylight', 'Shade', 'Kelvin']);
        $kelvin = $wbMode === 'Kelvin' ? $this->faker->numberBetween(25, 100) * 100 : null;
        $red = $this->faker->numberBetween(-9, 9);
        $blue = $this->faker->numberBetween(-9, 9);

        return [
            'name' => $name,
            'source' => RecipeSource::Fxw,
            'source_url' => 'https://fujixweekly.com/'.$this->faker->unique()->slug(3).'/',
            'block_index' => 0,
            'author' => 'Ritchie Roesch',
            'published_at' => $this->faker->dateTimeBetween('-6 years'),
            'image_url' => null,
            'film_simulation' => $simulation,
            'film_simulation_raw' => $simulation->value,
            'dynamic_range' => $this->faker->randomElement(DynamicRange::cases()),
            'grain_roughness' => $grain,
            'grain_size' => $grain === EffectLevel::Off ? null : $this->faker->randomElement(GrainSize::cases()),
            'color_chrome_effect' => $this->faker->randomElement(EffectLevel::cases()),
            'color_chrome_fx_blue' => $this->faker->randomElement(EffectLevel::cases()),
            'wb_mode' => $wbMode,
            'wb_kelvin' => $kelvin,
            'wb_shift_red' => $red,
            'wb_shift_blue' => $blue,
            'white_balance_raw' => sprintf('%s, %+d Red & %+d Blue', $kelvin ? "{$kelvin}K" : $wbMode, $red, $blue),
            'highlight' => $this->faker->numberBetween(-4, 8) / 2,
            'shadow' => $this->faker->numberBetween(-4, 8) / 2,
            'color' => $simulation->family() === 'mono' ? null : $this->faker->numberBetween(-4, 4),
            'sharpness' => $this->faker->numberBetween(-4, 4),
            'high_iso_nr' => $this->faker->numberBetween(-4, 4),
            'clarity' => $this->faker->numberBetween(-5, 5),
            'iso_raw' => 'Auto, up to ISO 6400',
            'iso_max' => 6400,
            'exposure_compensation' => '0 to +1 (typically)',
            'imported_at' => now(),
        ];
    }

    public function mine(): static
    {
        return $this->state([
            'source' => RecipeSource::Mine,
            'source_url' => fn (array $attributes) => 'mine:'.$attributes['name'],
            'author' => null,
        ]);
    }

    public function manual(): static
    {
        return $this->state([
            'source' => RecipeSource::Manual,
            'source_url' => null,
            'author' => null,
            'imported_at' => null,
        ]);
    }

    public function needsReview(): static
    {
        return $this->state([
            'film_simulation' => null,
            'film_simulation_raw' => 'Any (See Below)',
            'needs_review' => true,
        ]);
    }

    public function favorite(int $rating = 5): static
    {
        return $this->state(['is_favorite' => true, 'rating' => $rating]);
    }
}
