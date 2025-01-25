<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Facades;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Facades\Sprout;
use Sprout\Tests\Unit\UnitTestCase;

class SproutFacadeTest extends UnitTestCase
{
    #[Test]
    public function usesCorrectInstance(): void
    {
        $sprout = $this->app->make('sprout');

        $this->assertEquals($sprout, Sprout::getFacadeRoot());
    }
}
