<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;

abstract class UnitTestCase extends TestCase
{
    use WithWorkbench;
    use RefreshDatabase;

    protected $enablesPackageDiscoveries = true;
}
