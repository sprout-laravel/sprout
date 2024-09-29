<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Workbench\App\Models\TenantChild;
use Workbench\App\Models\TenantChildren;
use Workbench\App\Models\TenantModel;
use Workbench\Database\Factories\TenantChildFactory;
use Workbench\Database\Factories\TenantChildrenFactory;
use Workbench\Database\Factories\TenantModelFactory;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

    }
}
