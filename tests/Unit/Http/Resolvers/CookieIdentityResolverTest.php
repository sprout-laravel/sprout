<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Http\Resolvers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Http\Resolvers\CookieIdentityResolver;
use Sprout\Support\ResolutionHook;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\resolver;
use function Sprout\tenancy;

class CookieIdentityResolverTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    protected function defineRoutes($router): void
    {
        $router->tenanted(function () {
            Route::get('/tenant', function () {
            })->name('tenant-route');
        }, 'cookie');
    }

    protected function withCustomCookieName(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.cookie.cookie', 'Custom-Cookie-Name');
        });
    }

    protected function withCustomOptions(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.cookie.options', [
                'httpOnly' => true,
                'secure'   => true,
                'sameSite' => true,
            ]);
        });
    }

    protected function withCustomCookieNamePattern(Application $app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.cookie.cookie', '{Tenancy}-{tenancy}-{Resolver}-{resolver}');
        });
    }

    #[Test]
    public function isRegisteredAndCanBeAccessed(): void
    {
        $resolver = resolver('cookie');

        $this->assertInstanceOf(CookieIdentityResolver::class, $resolver);
        $this->assertSame('{Tenancy}-Identifier', $resolver->getCookieName());
        $this->assertEmpty($resolver->getOptions());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test]
    public function replacesPlaceholdersInCookieName(): void
    {
        $resolver = resolver('cookie');
        $tenancy  = tenancy();

        $this->assertInstanceOf(CookieIdentityResolver::class, $resolver);
        $this->assertSame('{Tenancy}-Identifier', $resolver->getCookieName());
        $this->assertSame(ucfirst($tenancy->getName()) . '-Identifier', $resolver->getRequestCookieName($tenancy));
    }

    #[Test, DefineEnvironment('withCustomCookieName')]
    public function acceptsCustomCookieName(): void
    {
        $resolver = resolver('cookie');

        $this->assertInstanceOf(CookieIdentityResolver::class, $resolver);
        $this->assertNotSame('{Tenancy}-Identifier', $resolver->getCookieName());
        $this->assertSame('Custom-Cookie-Name', $resolver->getCookieName());
        $this->assertEmpty($resolver->getOptions());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomCookieName')]
    public function replacesAllPlaceholders(): void
    {
        $resolver = resolver('cookie');

        $this->assertInstanceOf(CookieIdentityResolver::class, $resolver);
        $this->assertNotSame('{Tenancy}-Identifier', $resolver->getCookieName());
        $this->assertSame('Custom-Cookie-Name', $resolver->getCookieName());
        $this->assertEmpty($resolver->getOptions());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomOptions')]
    public function acceptsCustomOptions(): void
    {
        $resolver = resolver('cookie');
        $options  = $resolver->getOptions();

        $this->assertInstanceOf(CookieIdentityResolver::class, $resolver);
        $this->assertSame('{Tenancy}-Identifier', $resolver->getCookieName());
        $this->assertNotEmpty($options);
        $this->assertArrayHasKey('httpOnly', $options);
        $this->assertArrayHasKey('secure', $options);
        $this->assertArrayHasKey('sameSite', $options);
        $this->assertTrue($options['httpOnly']);
        $this->assertTrue($options['secure']);
        $this->assertTrue($options['sameSite']);
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test, DefineEnvironment('withCustomCookieNamePattern')]
    public function replacesAllPlaceholdersInCookieName(): void
    {
        $resolver = resolver('cookie');
        $tenancy  = tenancy();

        $this->assertInstanceOf(CookieIdentityResolver::class, $resolver);
        $this->assertSame('{Tenancy}-{tenancy}-{Resolver}-{resolver}', $resolver->getCookieName());
        $this->assertSame(
            ucfirst($tenancy->getName()) . '-' . $tenancy->getName() . '-' . ucfirst($resolver->getName()) . '-' . $resolver->getName(),
            $resolver->getRequestCookieName($tenancy)
        );
        $this->assertEmpty($resolver->getOptions());
        $this->assertSame([ResolutionHook::Routing], $resolver->getHooks());
    }

    #[Test]
    public function canGenerateRoutesForATenant(): void
    {
        $resolver = resolver('cookie');
        $tenancy  = tenancy();
        $tenant   = TenantModel::factory()->createOne();

        $this->assertSame('http://localhost/tenant', $resolver->route('tenant-route', $tenancy, $tenant));
        $this->assertSame('/tenant', $resolver->route('tenant-route', $tenancy, $tenant, absolute: false));
    }
}
