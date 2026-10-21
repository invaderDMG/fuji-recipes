<?php

namespace Database\Factories;

use App\Models\Sensor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sensor>
 */
class SensorFactory extends Factory
{
    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'slug' => $slug,
            'label' => ucwords(str_replace('-', ' ', $slug)),
            'cameras' => null,
        ];
    }
}
