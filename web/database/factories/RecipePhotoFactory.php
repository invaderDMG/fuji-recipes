<?php

namespace Database\Factories;

use App\Enums\PhotoSource;
use App\Models\Recipe;
use App\Models\RecipePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipePhoto>
 */
class RecipePhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'source' => PhotoSource::Sample,
            'url' => 'https://i0.wp.com/fujixweekly.com/wp-content/uploads/2024/05/'.$this->faker->unique()->numerify('########').'_h.jpg',
            'caption' => $this->faker->sentence(4),
            'width' => 1600,
            'height' => 1067,
            'position' => 0,
        ];
    }

    public function mine(): static
    {
        return $this->state([
            'source' => PhotoSource::Mine,
            'url' => null,
            'path' => RecipePhoto::DIRECTORY.'/'.$this->faker->uuid().'.jpg',
            'width' => null,
            'height' => null,
        ]);
    }
}
