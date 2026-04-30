<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Attributes;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\Override;
use Sprout\Contracts\ServiceOverride;
use Sprout\Managers\ServiceOverrideManager;
use Sprout\Tests\Unit\UnitTestCase;
use function Sprout\sprout;
use function Sprout\tenancy;

class OverrideTest extends UnitTestCase
{
    #[Test]
    public function resolvesServiceOverrides(): void
    {
        $manager = $this->app->make(ServiceOverrideManager::class);

        $callback1 = static function (#[Override('session')] ServiceOverride $override) {
            return $override;
        };

        $callback2 = static function (#[Override('filesystem')] ServiceOverride $override) {
            return $override;
        };

        $this->assertSame($manager->get('session'), $this->app->call($callback1));
        $this->assertSame($manager->get('filesystem'), $this->app->call($callback2));
    }

    #[Test]
    public function resolveDelegatesToTheServiceOverrideManager(): void
    {
        $expected = Mockery::mock(ServiceOverride::class);

        // ServiceOverrideManager is `final`; partial-mock a real instance.
        $app     = Mockery::mock(Application::class);
        $manager = Mockery::mock(new ServiceOverrideManager($app));
        $manager->shouldReceive('get')->with('session')->andReturn($expected)->once();

        $container = Mockery::mock(Container::class, function (MockInterface $mock) use ($manager) {
            $mock->shouldReceive('make')->with(ServiceOverrideManager::class)->andReturn($manager)->once();
        });

        $attribute = new Override('session');

        $this->assertSame($expected, $attribute->resolve($attribute, $container));
    }
}
