<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Support;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Support\ResolutionHelper;
use Sprout\Support\ResolutionHook;
use Sprout\Tests\Unit\UnitTestCase;

class ResolutionHelperTest extends UnitTestCase
{
    #[Test]
    public function parsesMiddlewareOptions(): void
    {
        [$resolverName, $tenancyName] = ResolutionHelper::parseOptions([]);

        $this->assertNull($resolverName);
        $this->assertNull($tenancyName);

        [$resolverName, $tenancyName] = ResolutionHelper::parseOptions(['test']);

        $this->assertNotNull($resolverName);
        $this->assertSame('test', $resolverName);
        $this->assertNull($tenancyName);

        [$resolverName, $tenancyName] = ResolutionHelper::parseOptions(['test', 'more']);

        $this->assertNotNull($resolverName);
        $this->assertSame('test', $resolverName);
        $this->assertNotNull($tenancyName);
        $this->assertSame('more', $tenancyName);
    }

    #[Test]
    public function throwsExceptionWhenHandlingResolutionForUnsupportedHook(): void
    {
        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The resolution hook [Booting] is not supported');

        /** @var \Illuminate\Http\Request $fakeRequest */
        $fakeRequest = $this->mock(Request::class);

        ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Booting);
    }
}
