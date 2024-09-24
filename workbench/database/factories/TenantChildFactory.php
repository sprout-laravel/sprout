<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\TenantChild;

/**
 * @template TModel of \Workbench\App\Models\TenantChild
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class TenantChildFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = TenantChild::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
