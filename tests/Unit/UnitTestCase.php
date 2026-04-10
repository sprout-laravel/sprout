<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;

abstract class UnitTestCase extends TestCase
{
    use WithWorkbench;

    protected $enablesPackageDiscoveries = true;
}
