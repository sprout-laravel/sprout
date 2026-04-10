<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Facades;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Facades\Overrides;
use Sprout\Core\Tests\Unit\UnitTestCase;

class OverridesFacadeTest extends UnitTestCase
{
    #[Test]
    public function usesCorrectInstance(): void
    {
        $this->assertEquals(\Sprout\Core\sprout()->overrides(), Overrides::getFacadeRoot());
    }
}
