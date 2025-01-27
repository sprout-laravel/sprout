<?php
declare(strict_types=1);

namespace Sprout\Tests\Feature;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use WithWorkbench;

    protected $enablesPackageDiscoveries = true;
}
