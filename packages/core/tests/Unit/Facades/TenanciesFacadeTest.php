<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Facades;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Facades\Tenancies;
use Sprout\Core\Tests\Unit\UnitTestCase;

class TenanciesFacadeTest extends UnitTestCase
{
    #[Test]
    public function usesCorrectInstance(): void
    {
        $this->assertEquals(\Sprout\Core\sprout()->tenancies(), Tenancies::getFacadeRoot());
    }
}
