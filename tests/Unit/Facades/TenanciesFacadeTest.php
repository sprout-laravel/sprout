<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Facades;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Facades\Sprout;
use Sprout\Facades\Tenancies;
use Sprout\Tests\Unit\UnitTestCase;

class TenanciesFacadeTest extends UnitTestCase
{
    #[Test]
    public function usesCorrectInstance(): void
    {
        $this->assertEquals(\Sprout\sprout()->tenancies(), Tenancies::getFacadeRoot());
    }
}
