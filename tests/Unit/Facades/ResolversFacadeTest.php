<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Facades;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Facades\Resolvers;
use Sprout\Tests\Unit\UnitTestCase;
use function Sprout\sprout;

class ResolversFacadeTest extends UnitTestCase
{
    #[Test]
    public function usesCorrectInstance(): void
    {
        $this->assertEquals(sprout()->resolvers(), Resolvers::getFacadeRoot());
    }
}
