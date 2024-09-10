<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\TenantChildren;

/**
 * @template TModel of \Workbench\App\Models\TenantChildren
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class TenantChildrenFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = TenantChildren::class;

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
