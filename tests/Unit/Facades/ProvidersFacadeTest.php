<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Facades;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Facades\Providers;
use Sprout\Facades\Sprout;
use Sprout\Tests\Unit\UnitTestCase;

class ProvidersFacadeTest extends UnitTestCase
{
    #[Test]
    public function usesCorrectInstance(): void
    {
        $this->assertEquals(\Sprout\sprout()->providers(), Providers::getFacadeRoot());
    }
}
