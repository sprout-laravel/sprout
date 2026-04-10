<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides\Auth;

use Carbon\Carbon;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Overrides\Auth\SproutAuthCacheTokenRepository;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;

class SproutAuthCacheTokenRepositoryTest extends UnitTestCase
{
    private function getSprout(Application $app, ?Tenancy $tenancy, ?Tenant $tenant, int $callCount = 1): Sprout
    {
        $sprout = new Sprout($app, new SettingsRepository());

        if ($tenancy !== null && $tenant !== null) {
            $sprout->setCurrentTenancy($tenancy);

            /**
             * @var \Sprout\Contracts\Tenancy&\Mockery\MockInterface $tenancy
             * @var \Sprout\Contracts\Tenancy&\Mockery\MockInterface $tenant
             */

            $tenancy->shouldReceive('check')->andReturn(true)->times($callCount);
            $tenancy->shouldReceive('tenant')->andReturn($tenant)->times($callCount);
            $tenancy->shouldReceive('getName')->andReturn('my-tenancy')->times($callCount);
            $tenant->shouldReceive('getTenantKey')->andReturn(7)->times($callCount);
        }

        return $sprout;
    }

    private function mockUser(int $callCount = 1): CanResetPassword&MockInterface
    {
        return Mockery::mock(CanResetPassword::class, static function (MockInterface $mock) use ($callCount) {
            $mock->shouldReceive('getEmailForPasswordReset')->andReturn('test@email.com')->times($callCount);
        });
    }

    private function mockStoreGet(?Tenancy $tenancy, string $token, $hasher, bool $return = true, ?\Closure $expiryAdjuster = null): Repository&MockInterface
    {
        $storedToken = $return ? $hasher->make($token) : null;

        return Mockery::mock(Repository::class, static function (MockInterface $mock) use ($expiryAdjuster, $storedToken, $tenancy) {
            $mock->shouldReceive('get')
                 ->withArgs([
                     hash('sha256', 'my-prefix' . ($tenancy !== null ? '.my-tenancy.7' : '') . 'test@email.com'),
                 ])
                 ->andReturn($storedToken ? [
                     $storedToken,
                     ($expiryAdjuster ? $expiryAdjuster(Carbon::now()) : Carbon::now()->subMinute())->format('Y-m-d H:i:s'),
                 ] : null)
                 ->once();
        });
    }

    #[Test, DataProvider('multitenancyContextDataProvider')]
    public function returnsTheCorrectPrefix(bool $inContext): void
    {
        $store = Mockery::mock(Repository::class, static function (MockInterface $mock) {

        });

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        if ($inContext) {
            $sprout->markAsInContext();
        }

        if ($inContext) {
            $sprout->setCurrentTenancy(Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($inContext) {
                $mock->shouldReceive('check')->andReturn($inContext)->once();
                $mock->shouldReceive('tenant')->andReturn(Mockery::mock(Tenant::class, static function (MockInterface $mock) {
                    $mock->shouldReceive('getTenantKey')->andReturn(7)->once();
                    $mock->shouldNotReceive('getTenantResourceKey');
                }))->once();

                $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
            }));
        }

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $this->app->make('hash'),
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $prefix = 'my-prefix' . ($inContext ? '.my-tenancy.7' : '');

        $this->assertEquals($prefix, $repository->getPrefix());
    }

    #[Test, DataProvider('multitenancyContextDataProvider')]
    public function returnsTheCorrectPrefixForTenantsWithResources(bool $inContext): void
    {
        $store = Mockery::mock(Repository::class, static function (MockInterface $mock) {

        });

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        if ($inContext) {
            $sprout->markAsInContext();
        }

        if ($inContext) {
            $sprout->setCurrentTenancy(Mockery::mock(Tenancy::class, static function (MockInterface $mock) use ($inContext) {
                $mock->shouldReceive('check')->andReturn($inContext)->once();
                $mock->shouldReceive('tenant')->andReturn(Mockery::mock(Tenant::class, TenantHasResources::class, static function (MockInterface $mock) {
                    $mock->shouldNotReceive('getTenantKey');
                    $mock->shouldReceive('getTenantResourceKey')->andReturn('the-key')->once();
                }))->once();

                $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
            }));
        }

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $this->app->make('hash'),
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $prefix = 'my-prefix' . ($inContext ? '.my-tenancy.the-key' : '');

        $this->assertEquals($prefix, $repository->getPrefix());
    }

    #[Test]
    public function throwsExceptionWhenInContextWithoutTenancy(): void
    {
        $store = Mockery::mock(Repository::class, static function (MockInterface $mock) {

        });

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->markAsInContext();

        $this->expectException(TenancyMissingException::class);
        $this->expectExceptionMessage('There is no current tenancy');

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $this->app->make('hash'),
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $repository->getPrefix();
    }

    #[Test]
    public function throwsExceptionWhenInContextWithTenancyButNoTenant(): void
    {
        $store = Mockery::mock(Repository::class, static function (MockInterface $mock) {

        });

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->markAsInContext();

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('check')->andReturn(false)->once();
            $mock->shouldReceive('getName')->andReturn('my-tenancy')->once();
        });

        $sprout->setCurrentTenancy($tenancy);

        $this->expectException(TenantMissingException::class);
        $this->expectExceptionMessage('There is no current tenant for tenancy [my-tenancy]');

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $this->app->make('hash'),
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $repository->getPrefix();
    }

    #[Test, DataProvider('tenantContextDataProvider')]
    public function canCreateTokens(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = $this->getSprout($app, $tenancy, $tenant, 2);
        $user   = $this->mockUser(2);
        $hash   = null;

        $store = Mockery::mock(Repository::class, static function (MockInterface $mock) use ($tenancy, &$hash) {
            $mock->shouldReceive('forget')
                 ->withArgs([
                     hash('sha256', 'my-prefix' . ($tenancy !== null ? '.my-tenancy.7' : '') . 'test@email.com'),
                 ])
                 ->once();
            $mock->shouldReceive('put')
                 ->withArgs([
                     hash('sha256', 'my-prefix' . ($tenancy !== null ? '.my-tenancy.7' : '') . 'test@email.com'),
                     Mockery::on(static function ($arg) use (&$hash) {
                         if (is_array($arg)
                             && is_string($arg[0])
                             && is_string($arg[1])) {
                             $hash = $arg[0];

                             return true;
                         }

                         return false;
                     }),
                     3600,
                 ])
                 ->once();
        });

        $hasher = $this->app->make('hash');

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $hasher,
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $token = $repository->create($user);
        $this->assertTrue($hasher->check($token, $hash));
    }

    #[Test, DataProvider('tenantContextDataProvider')]
    public function canCheckIfExistingTokensExist(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = $this->getSprout($app, $tenancy, $tenant);
        $user   = $this->mockUser();
        $hasher = $this->app->make('hash');
        $token  = hash_hmac('sha256', Str::random(40), 'hash-key');
        $store  = $this->mockStoreGet($tenancy, $token, $hasher);

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $hasher,
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $this->assertTrue($repository->exists($user, $token));
    }

    #[Test, DataProvider('tenantContextDataProvider')]
    public function canCheckIfExpiredExistingTokensExist(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = $this->getSprout($app, $tenancy, $tenant);
        $user   = $this->mockUser();
        $hasher = $this->app->make('hash');
        $token  = hash_hmac('sha256', Str::random(40), 'hash-key');
        $store  = $this->mockStoreGet(
            $tenancy,
            $token,
            $hasher,
            true,
            fn(Carbon $carbon) => $carbon->subMinutes(3700)
        );

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $hasher,
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $this->assertFalse($repository->exists($user, $token));
    }

    #[Test, DataProvider('tenantContextDataProvider')]
    public function canCheckIfNonExistingTokensExist(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = $this->getSprout($app, $tenancy, $tenant);
        $user   = $this->mockUser();
        $hasher = $this->app->make('hash');
        $token  = hash_hmac('sha256', Str::random(40), 'hash-key');
        $store  = $this->mockStoreGet(
            $tenancy,
            $token,
            $hasher,
            false
        );

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $hasher,
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $this->assertFalse($repository->exists($user, $token));
    }

    #[Test, DataProvider('tenantContextDataProvider')]
    public function canCheckIfExistingTokensWereRecentlyCreated(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = $this->getSprout($app, $tenancy, $tenant);
        $user   = $this->mockUser();
        $hasher = $this->app->make('hash');
        $token  = hash_hmac('sha256', Str::random(40), 'hash-key');
        $store  = $this->mockStoreGet(
            $tenancy,
            $token,
            $hasher,
            true,
            fn(Carbon $carbon) => $carbon->subSeconds(10)
        );

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $hasher,
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $this->assertTrue($repository->recentlyCreatedToken($user));
    }

    #[Test, DataProvider('tenantContextDataProvider')]
    public function canCheckIfExistingOldTokensWereRecentlyCreated(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = $this->getSprout($app, $tenancy, $tenant);
        $user   = $this->mockUser();
        $hasher = $this->app->make('hash');
        $token  = hash_hmac('sha256', Str::random(40), 'hash-key');
        $store  = $this->mockStoreGet(
            $tenancy,
            $token,
            $hasher,
            true,
            fn(Carbon $carbon) => $carbon->subMinute()
        );

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $hasher,
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $this->assertFalse($repository->recentlyCreatedToken($user));
    }

    #[Test, DataProvider('tenantContextDataProvider')]
    public function canCheckIfExpiredExistingTokensWereRecentlyCreated(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = $this->getSprout($app, $tenancy, $tenant);
        $user   = $this->mockUser();
        $hasher = $this->app->make('hash');
        $token  = hash_hmac('sha256', Str::random(40), 'hash-key');
        $store  = $this->mockStoreGet(
            $tenancy,
            $token,
            $hasher,
            true,
            fn(Carbon $carbon) => $carbon->subMinutes(3700)
        );

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $hasher,
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $this->assertFalse($repository->recentlyCreatedToken($user));
    }

    #[Test, DataProvider('tenantContextDataProvider')]
    public function canCheckIfNonExistingTokensWereRecentlyCreated(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = $this->getSprout($app, $tenancy, $tenant);
        $user   = $this->mockUser();
        $hasher = $this->app->make('hash');
        $token  = hash_hmac('sha256', Str::random(40), 'hash-key');
        $store  = $this->mockStoreGet(
            $tenancy,
            $token,
            $hasher,
            false
        );

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $hasher,
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $this->assertFalse($repository->recentlyCreatedToken($user));
    }

    #[Test, DataProvider('tenantContextDataProvider')]
    public function canDeleteTokens(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = $this->getSprout($app, $tenancy, $tenant);
        $user   = $this->mockUser();
        $store = Mockery::mock(Repository::class, static function (MockInterface $mock) use ($tenancy) {
            $mock->shouldReceive('forget')
                 ->withArgs([
                     hash('sha256', 'my-prefix' . ($tenancy !== null ? '.my-tenancy.7' : '') . 'test@email.com'),
                 ])
                 ->once();
        });

        $repository = new SproutAuthCacheTokenRepository(
            $sprout,
            $store,
            $this->app->make('hash'),
            'hash-key',
            3600,
            60,
            'my-prefix'
        );

        $repository->delete($user);
    }

    public static function multitenancyContextDataProvider(): array
    {
        return [
            'not in context' => [false],
            'in context'     => [true],
        ];
    }

    public static function tenantContextDataProvider(): array
    {
        $tenancy = Mockery::mock(Tenancy::class);
        $tenant  = Mockery::mock(Tenant::class);

        return [
            'tenant context'    => [$tenancy, $tenant],
        ];
    }
}
