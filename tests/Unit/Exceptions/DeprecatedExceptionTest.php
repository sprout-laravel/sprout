<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Exceptions;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\DeprecatedException;
use Sprout\Exceptions\SproutException;
use Sprout\Tests\Unit\UnitTestCase;

class DeprecatedExceptionTest extends UnitTestCase
{
    #[Test]
    public function makeReturnsAnExceptionWithTheExpectedMessage(): void
    {
        $exception = DeprecatedException::make();

        $this->assertInstanceOf(SproutException::class, $exception);
        $this->assertSame('This feature is deprecated', $exception->getMessage());
    }
}
