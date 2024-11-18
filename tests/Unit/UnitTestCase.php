<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit;

use Orchestra\Testbench\Concerns\WithWorkbench;

abstract class UnitTestCase extends \Orchestra\Testbench\TestCase
{
    use WithWorkbench;

    protected $enablesPackageDiscoveries = true;
}
