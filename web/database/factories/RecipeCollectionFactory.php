<?php

namespace Database\Factories;

use App\Models\RecipeCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeCollection>
 */
class RecipeCollectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'description' => null,
        ];
    }
}
