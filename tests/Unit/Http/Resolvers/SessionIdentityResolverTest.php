<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Http\Resolvers;

use Illuminate\Contracts\Foundation\Application;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Http\Resolvers\SessionIdentityResolver;
use Sprout\Support\ResolutionHook;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\resolver;
use function Sprout\tenancy;

class SessionIdentityResolverTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    protected function withCustomSessionName(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.session.session', 'Custom-Session-Name');
        });
    }

    protected function withCustomSessionNamePattern(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.session.session', '{Tenancy}-{tenancy}-{Resolver}-{resolver}');
        });
    }

    #[Test]
    public function isRegisteredAndCanBeAccessed(): void
    {
        $resolver = resolver('session');

        $this->assertInstanceOf(SessionIdentityResolver::class, $resolver);
        $this->assertSame('multitenancy.{tenancy}', $resolver->getSessionName());
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
    }

    #[Test]
    public function replacesPlaceholdersInSessionName(): void
    {
        $resolver = resolver('session');
        $tenancy  = tenancy();

        $this->assertInstanceOf(SessionIdentityResolver::class, $resolver);
        $this->assertSame('multitenancy.{tenancy}', $resolver->getSessionName());
        $this->assertSame('multitenancy.' . $tenancy->getName(), $resolver->getRequestSessionName($tenancy));
    }

    #[Test, DefineEnvironment('withCustomSessionName')]
    public function acceptsCustomSessionName(): void
    {
        $resolver = resolver('session');

        $this->assertInstanceOf(SessionIdentityResolver::class, $resolver);
        $this->assertNotSame('multitenancy.{tenancy}', $resolver->getSessionName());
        $this->assertSame('Custom-Session-Name', $resolver->getSessionName());
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomSessionName')]
    public function replacesAllPlaceholders(): void
    {
        $resolver = resolver('session');

        $this->assertInstanceOf(SessionIdentityResolver::class, $resolver);
        $this->assertNotSame('multitenancy.{tenancy}', $resolver->getSessionName());
        $this->assertSame('Custom-Session-Name', $resolver->getSessionName());
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomSessionNamePattern')]
    public function replacesAllPlaceholdersInSessionName(): void
    {
        $resolver = resolver('session');
        $tenancy  = tenancy();

        $this->assertInstanceOf(SessionIdentityResolver::class, $resolver);
        $this->assertSame('{Tenancy}-{tenancy}-{Resolver}-{resolver}', $resolver->getSessionName());
        $this->assertSame(
            ucfirst($tenancy->getName()) . '-' . $tenancy->getName() . '-' . ucfirst($resolver->getName()) . '-' . $resolver->getName(),
            $resolver->getRequestSessionName($tenancy)
        );
        $this->assertSame([ResolutionHook::Middleware], $resolver->getHooks());
    }
}
