<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\NoResourcesTenantModel;

/**
 * @template TModel of \Workbench\App\Models\NoResourcesTenantModel
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class NoResourcesTenantModelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = NoResourcesTenantModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'       => $this->faker->company(),
            'identifier' => $this->faker->unique()->slug(),
            'active'     => true,
        ];
    }
}
