<?php
namespace Database\Factories;

use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Resource>
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        return [
            'name'        => fake()->words(3, true),
            'description' => fake()->sentence(),
            'location'    => fake()->address(),
            'capacity'    => fake()->numberBetween(2, 20),
            'features'    => ['пар', 'веники', 'душ'],
            'is_active'   => true,
        ];
    }

    /**
     * Индикация неактивного ресурса
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}