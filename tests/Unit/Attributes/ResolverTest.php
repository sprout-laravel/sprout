<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Attributes;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Attributes\Resolver;
use Sprout\Core\Contracts\IdentityResolver;
use Sprout\Core\Managers\IdentityResolverManager;
use Sprout\Core\Tests\Unit\UnitTestCase;

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
}
