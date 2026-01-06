<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Facades;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Facades\Providers;
use Sprout\Tests\Unit\UnitTestCase;
use function Sprout\sprout;

class ProvidersFacadeTest extends UnitTestCase
{
    #[Test]
    public function usesCorrectInstance(): void
    {
        $this->assertEquals(sprout()->providers(), Providers::getFacadeRoot());
    }
}
