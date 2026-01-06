<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Facades;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Facades\Tenancies;
use Sprout\Tests\Unit\UnitTestCase;
use function Sprout\sprout;

class TenanciesFacadeTest extends UnitTestCase
{
    #[Test]
    public function usesCorrectInstance(): void
    {
        $this->assertEquals(sprout()->tenancies(), Tenancies::getFacadeRoot());
    }
}
