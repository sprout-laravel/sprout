<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\TenantModel;

/**
 * @template TModel of \Workbench\App\TenantModel
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class TenantModelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = TenantModel::class;

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
