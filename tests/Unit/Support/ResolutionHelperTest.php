<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\IdentityResolver;
use Sprout\Contracts\IdentityResolverUsesParameters;
use Sprout\Contracts\Tenancy;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\NoTenantFoundException;
use Sprout\Sprout;
use Sprout\Support\ResolutionHelper;
use Sprout\Support\ResolutionHook;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;

use function Sprout\resolver;
use function Sprout\sprout;
use function Sprout\tenancy;

class ResolutionHelperTest extends UnitTestCase
{
    use RefreshDatabase;

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

        /** @var Request $fakeRequest */
        $fakeRequest = Mockery::mock(Request::class);

        ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Booting, sprout());
    }

    #[Test]
    public function returnsFalseIfThereIsAlreadyATenant(): void
    {
        /** @var Tenancy<TenantModel> $tenancy */
        $tenancy = tenancy();

        $tenancy->setTenant(TenantModel::factory()->createOne());

        /** @var IdentityResolver $resolver */
        $resolver = resolver('path');

        /** @var Request $fakeRequest */
        $fakeRequest = $this->mock(Request::class);

        $this->assertTrue($tenancy->check());
        $this->assertTrue($resolver->canResolve($fakeRequest, $tenancy, ResolutionHook::Routing));
        $this->assertFalse(ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout(), $resolver->getName(), $tenancy->getName()));
    }

    #[Test]
    public function returnsFalseIfTheResolverCannotResolve(): void
    {
        /** @var Tenancy<TenantModel> $tenancy */
        $tenancy = tenancy();

        /** @var IdentityResolver $resolver */
        $resolver = resolver('path');

        $tenancy->setTenant(TenantModel::factory()->createOne())
                ->resolvedVia($resolver)
                ->resolvedAt(ResolutionHook::Routing);

        /** @var Request $fakeRequest */
        $fakeRequest = $this->mock(Request::class);

        $this->assertTrue($tenancy->check());
        $this->assertFalse($resolver->canResolve($fakeRequest, $tenancy, ResolutionHook::Routing));
        $this->assertFalse(ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout(), $resolver->getName(), $tenancy->getName()));
    }

    #[Test]
    public function resolvesTenantUsingRouteParameters(): void
    {
        $tenant = TenantModel::factory()->createOne();

        /** @var Tenancy<TenantModel> $tenancy */
        $tenancy = tenancy();

        /** @var IdentityResolver&IdentityResolverUsesParameters $resolver */
        $resolver = resolver('path');

        /** @var Route $fakeRoute */
        $fakeRoute = $this->mock(Route::class, function (MockInterface $mock) use ($tenant, $tenancy, $resolver) {
            $parameterName = $resolver->getRouteParameterName($tenancy);

            $mock->shouldReceive('hasParameter')
                 ->with($parameterName)
                 ->andReturn(true);

            $mock->shouldReceive('parameter')
                 ->with($parameterName)
                 ->andReturn($tenant->getTenantIdentifier());

            $mock->shouldReceive('forgetParameter')
                 ->with($parameterName);
        });

        /** @var Request $fakeRequest */
        $fakeRequest = $this->mock(Request::class, function (MockInterface $mock) use ($fakeRoute) {
            $mock->shouldReceive('route')->andReturn($fakeRoute);
        });

        $this->assertTrue(ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout(), $resolver->getName(), $tenancy->getName()));
        $this->assertSame($tenancy, sprout()->getCurrentTenancy());
        $this->assertTrue($tenancy->check());
        $this->assertTrue($tenant->is($tenancy->tenant()));
        $this->assertTrue($tenancy->wasResolved());
        $this->assertSame($resolver, $tenancy->resolver());
        $this->assertSame(ResolutionHook::Routing, $tenancy->hook());

        $tenancy->setTenant(null);

        $this->assertTrue(ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout()));
        $this->assertSame($tenancy, sprout()->getCurrentTenancy());
        $this->assertTrue($tenancy->check());
        $this->assertTrue($tenant->is($tenancy->tenant()));
        $this->assertTrue($tenancy->wasResolved());
        $this->assertSame($resolver, $tenancy->resolver());
        $this->assertSame(ResolutionHook::Routing, $tenancy->hook());
    }

    #[Test]
    public function throwsAnExceptionWhenUnableToIdentifyATenantFromTheRoute(): void
    {
        /** @var Tenancy<TenantModel> $tenancy */
        $tenancy = tenancy();

        /** @var IdentityResolver&IdentityResolverUsesParameters $resolver */
        $resolver = resolver('path');

        /** @var Route $fakeRoute */
        $fakeRoute = $this->mock(Route::class, function (MockInterface $mock) use ($tenancy, $resolver) {
            $parameterName = $resolver->getRouteParameterName($tenancy);

            $mock->shouldReceive('hasParameter')
                 ->with($parameterName)
                 ->andReturn(true);

            $mock->shouldReceive('parameter')
                 ->with($parameterName)
                 ->andReturn('fake-identifier');

            $mock->shouldReceive('forgetParameter')
                 ->with($parameterName);
        });

        /** @var Request $fakeRequest */
        $fakeRequest = $this->mock(Request::class, function (MockInterface $mock) use ($fakeRoute) {
            $mock->shouldReceive('route')->andReturn($fakeRoute);
        });

        $this->expectException(NoTenantFoundException::class);
        $this->expectExceptionMessage('No valid tenant [' . $tenancy->getName() . '] found [' . $resolver->getName() . ']');

        ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout(), $resolver->getName(), $tenancy->getName());

        $this->expectException(NoTenantFoundException::class);
        $this->expectExceptionMessage('No valid tenant [' . $tenancy->getName() . '] found [subdomain]');

        ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout());
    }

    #[Test]
    public function returnsFalseWhenUnableToIdentifyATenantFromTheRouteAndToldNotToThrow(): void
    {
        /** @var Tenancy<TenantModel> $tenancy */
        $tenancy = tenancy();

        /** @var IdentityResolver&IdentityResolverUsesParameters $resolver */
        $resolver = resolver('path');

        /** @var Route $fakeRoute */
        $fakeRoute = $this->mock(Route::class, function (MockInterface $mock) use ($tenancy, $resolver) {
            $parameterName = $resolver->getRouteParameterName($tenancy);

            $mock->shouldReceive('hasParameter')
                 ->with($parameterName)
                 ->andReturn(true);

            $mock->shouldReceive('parameter')
                 ->with($parameterName)
                 ->andReturn('fake-identifier');

            $mock->shouldReceive('forgetParameter')
                 ->with($parameterName);
        });

        /** @var Request $fakeRequest */
        $fakeRequest = $this->mock(Request::class, function (MockInterface $mock) use ($fakeRoute) {
            $mock->shouldReceive('route')->andReturn($fakeRoute);
        });

        $this->assertFalse(ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout(), $resolver->getName(), $tenancy->getName(), false));
        $this->assertSame($tenancy, sprout()->getCurrentTenancy());
        $this->assertFalse($tenancy->check());
        $this->assertFalse($tenancy->wasResolved());
        $this->assertNull($tenancy->resolver());
        $this->assertNull($tenancy->hook());

        $this->assertFalse(ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout(), throw: false));
        $this->assertSame($tenancy, sprout()->getCurrentTenancy());
        $this->assertFalse($tenancy->check());
        $this->assertFalse($tenancy->wasResolved());
        $this->assertNull($tenancy->resolver());
        $this->assertNull($tenancy->hook());
    }

    #[Test]
    public function resolvesTenantWithoutRouteParameters(): void
    {
        $tenant = TenantModel::factory()->createOne();

        /** @var Tenancy<TenantModel> $tenancy */
        $tenancy = tenancy();

        /** @var IdentityResolver&IdentityResolverUsesParameters $resolver */
        $resolver = resolver('path');

        /** @var Request $fakeRequest */
        $fakeRequest = $this->mock(Request::class, function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('route')->andReturnNull();

            $mock->shouldReceive('segment')
                 ->with(1)
                 ->andReturn($tenant->getTenantIdentifier());
        });

        $this->assertTrue(ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout(), $resolver->getName(), $tenancy->getName()));
        $this->assertSame($tenancy, sprout()->getCurrentTenancy());
        $this->assertTrue($tenancy->check());
        $this->assertTrue($tenant->is($tenancy->tenant()));
        $this->assertTrue($tenancy->wasResolved());
        $this->assertSame($resolver, $tenancy->resolver());
        $this->assertSame(ResolutionHook::Routing, $tenancy->hook());

        $tenancy->setTenant(null);

        $this->assertTrue(ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout()));
        $this->assertSame($tenancy, sprout()->getCurrentTenancy());
        $this->assertTrue($tenancy->check());
        $this->assertTrue($tenant->is($tenancy->tenant()));
        $this->assertTrue($tenancy->wasResolved());
        $this->assertSame($resolver, $tenancy->resolver());
        $this->assertSame(ResolutionHook::Routing, $tenancy->hook());
    }

    #[Test]
    public function throwsAnExceptionWhenUnableToIdentifyATenantFromTheRequest(): void
    {
        /** @var Tenancy<TenantModel> $tenancy */
        $tenancy = tenancy();

        /** @var IdentityResolver&IdentityResolverUsesParameters $resolver */
        $resolver = resolver('path');

        /** @var Request $fakeRequest */
        $fakeRequest = $this->mock(Request::class, function (MockInterface $mock) {
            $mock->shouldReceive('route')->andReturnNull();

            $mock->shouldReceive('segment')
                 ->with(1)
                 ->andReturn('fake-identifier');
        });

        $this->expectException(NoTenantFoundException::class);
        $this->expectExceptionMessage('No valid tenant [' . $tenancy->getName() . '] found [' . $resolver->getName() . ']');

        ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout(), $resolver->getName(), $tenancy->getName());

        $this->expectException(NoTenantFoundException::class);
        $this->expectExceptionMessage('No valid tenant [' . $tenancy->getName() . '] found [subdomain]');

        ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout());
    }

    #[Test]
    public function returnsFalseWhenUnableToIdentifyATenantFromTheRequestAndToldNotToThrow(): void
    {
        /** @var Tenancy<TenantModel> $tenancy */
        $tenancy = tenancy();

        /** @var IdentityResolver&IdentityResolverUsesParameters $resolver */
        $resolver = resolver('path');

        /** @var Request $fakeRequest */
        $fakeRequest = $this->mock(Request::class, function (MockInterface $mock) {
            $mock->shouldReceive('route')->andReturnNull();

            $mock->shouldReceive('segment')
                 ->with(1)
                 ->andReturn('fake-identifier');
        });

        $this->assertFalse(ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout(), $resolver->getName(), $tenancy->getName(), false));
        $this->assertSame($tenancy, sprout()->getCurrentTenancy());
        $this->assertFalse($tenancy->check());
        $this->assertFalse($tenancy->wasResolved());
        $this->assertNull($tenancy->resolver());
        $this->assertNull($tenancy->hook());

        $tenancy->setTenant(null);

        $this->assertFalse(ResolutionHelper::handleResolution($fakeRequest, ResolutionHook::Routing, sprout(), throw: false));
        $this->assertSame($tenancy, sprout()->getCurrentTenancy());
        $this->assertFalse($tenancy->check());
        $this->assertFalse($tenancy->wasResolved());
        $this->assertNull($tenancy->resolver());
        $this->assertNull($tenancy->hook());
    }

    #[Test]
    public function returnsFalseEarlyWhenIdentityIsNullInOptionalMode(): void
    {
        /** @var Tenancy<TenantModel> $tenancy */
        $tenancy = tenancy();

        /** @var IdentityResolver&IdentityResolverUsesParameters $resolver */
        $resolver = resolver('path');

        /** @var Request $fakeRequest */
        $fakeRequest = $this->mock(Request::class, function (MockInterface $mock) {
            $mock->shouldReceive('route')->andReturnNull();
            $mock->shouldReceive('segment')->with(1)->andReturnNull();
        });

        // optional=true, throw=true — but optional should short-circuit before any throw.
        $this->assertFalse(
            ResolutionHelper::handleResolution(
                $fakeRequest,
                ResolutionHook::Routing,
                sprout(),
                $resolver->getName(),
                $tenancy->getName(),
                throw   : true,
                optional: true,
            ),
        );

        $this->assertFalse($tenancy->check());
        $this->assertFalse($tenancy->wasResolved());
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.resolver', 'path');
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('multitenancy.resolvers.subdomain.domain', 'localhost');
        });
    }

    protected function mockApp(): Application&MockInterface
    {
        return Mockery::mock(Application::class, static function ($mock) {
        });
    }

    protected function getSprout(Application $app): Sprout
    {
        return new Sprout($app, new SettingsRepository());
    }
}
