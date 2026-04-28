<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Attributes;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\Resolver;
use Sprout\Contracts\IdentityResolver;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Tests\Unit\UnitTestCase;

class ResolverTest extends UnitTestCase
{
    protected function defineEnvironment($app)
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.subdomain.domain', 'localhost');
        });
    }

    #[Test]
    public function resolvesIdentityResolver(): void
    {
        $manager = $this->app->make(IdentityResolverManager::class);

        $callback1 = static function (#[Resolver] IdentityResolver $resolver) {
            return $resolver;
        };

        $callback2 = static function (#[Resolver('subdomain')] IdentityResolver $resolver) {
            return $resolver;
        };

        $this->assertSame($manager->get(), $this->app->call($callback1));
        $this->assertSame($manager->get('subdomain'), $this->app->call($callback2));
    }

    #[Test]
    public function resolveDelegatesToTheIdentityResolverManager(): void
    {
        $expected = Mockery::mock(IdentityResolver::class);

        // IdentityResolverManager is `final`; partial-mock a real instance.
        $app     = Mockery::mock(Application::class);
        $manager = Mockery::mock(new IdentityResolverManager($app));
        $manager->shouldReceive('get')->with('subdomain')->andReturn($expected)->once();

        $container = Mockery::mock(Container::class, function (MockInterface $mock) use ($manager) {
            $mock->shouldReceive('make')->with(IdentityResolverManager::class)->andReturn($manager)->once();
        });

        $attribute = new Resolver('subdomain');

        $this->assertSame($expected, $attribute->resolve($attribute, $container));
    }
}
