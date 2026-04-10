<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Overrides\Auth;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Exceptions\TenancyMissingException;
use Sprout\Core\Exceptions\TenantMissingException;
use Sprout\Core\Overrides\Auth\SproutAuthDatabaseTokenRepository;
use Sprout\Core\Sprout;
use Sprout\Core\Support\SettingsRepository;
use Sprout\Core\Tests\Unit\UnitTestCase;

class SproutAuthDatabaseTokenRepositoryTest extends UnitTestCase
{
    private function getSprout(Application $app, ?Tenancy $tenancy, ?Tenant $tenant, int $callCount = 1): Sprout
    {
        $sprout = new Sprout($app, new SettingsRepository());

        if ($tenancy !== null && $tenant !== null) {
            $sprout->setCurrentTenancy($tenancy);

            /**
             * @var \Sprout\Core\Contracts\Tenancy&\Mockery\MockInterface $tenancy
             * @var \Sprout\Core\Contracts\Tenancy&\Mockery\MockInterface $tenant
             */

            $tenancy->shouldReceive('check')->andReturn(true)->times($callCount);
            $tenancy->shouldReceive('key')->andReturn(7)->times($callCount);
            $tenancy->shouldReceive('getName')->andReturn('my-tenancy')->times($callCount);
        }

        return $sprout;
    }

    private function mockUser(int $callCount = 1): CanResetPassword&MockInterface
    {
        return Mockery::mock(CanResetPassword::class, static function (MockInterface $mock) use ($callCount) {
            $mock->shouldReceive('getEmailForPasswordReset')->andReturn('test@email.com')->times($callCount);
        });
    }

    private function mockConnectionGet(?Tenancy $tenancy, string $token, $hasher, bool $return = true, ?\Closure $expiryAdjuster = null): Connection&MockInterface
    {
        $storedToken = $return ? $hasher->make($token) : null;

        return Mockery::mock(Connection::class, static function (MockInterface $mock) use ($expiryAdjuster, $storedToken, $tenancy) {
            $builder = Mockery::mock(Builder::class, static function (MockInterface $mock) use ($storedToken, $expiryAdjuster, $tenancy) {
                $mock->shouldReceive('where')
                     ->withArgs([
                         'email',
                         'test@email.com',
                     ])
                     ->andReturnSelf()
                     ->once();

                if ($tenancy !== null) {
                    $mock->shouldReceive('where')
                         ->withArgs([
                             'tenancy',
                             'my-tenancy',
                         ])
                         ->andReturnSelf()
                         ->once();
                    $mock->shouldReceive('where')
                         ->withArgs([
                             'tenant_id',
                             '7',
                         ])
                         ->andReturnSelf()
                         ->once();
                }

                $mock->shouldReceive('first')
                     ->andReturn($storedToken ? (object)[
                         'token'      => $storedToken,
                         'created_at' => ($expiryAdjuster ? $expiryAdjuster(Carbon::now()) : Carbon::now()->subMinute())->format('Y-m-d H:i:s'),
                     ] : null)
                     ->once();
            });

            $mock->shouldReceive('table')
                 ->withArgs(['my_passwords'])
                 ->andReturn($builder)
                 ->once();
        });
    }

    #[Test]
    public function throwsExceptionWhenInContextWithoutTenancy(): void
    {
        $connection = Mockery::mock(Connection::class);

        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->markAsInContext();

        $this->expectException(TenancyMissingException::class);
        $this->expectExceptionMessage('There is no current tenancy');

        $repository = new SproutAuthDatabaseTokenRepository(
            $sprout,
            $connection,
            $this->app->make('hash'),
            'my_passwords',
            'hash-key',
            3600,
            60
        );

        $repository->create($this->mockUser(2));
    }

    #[Test]
    public function throwsExceptionWhenInContextWithTenancyButNoTenant(): void
    {
        $connection = Mockery::mock(Connection::class);

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

        $repository = new SproutAuthDatabaseTokenRepository(
            $sprout,
            $connection,
            $this->app->make('hash'),
            'my_passwords',
            'hash-key',
            3600,
            60
        );

        $repository->create($this->mockUser(2));
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

        $connection = Mockery::mock(Connection::class, static function (MockInterface $mock) use ($tenancy) {
            $builder = Mockery::mock(Builder::class, static function (MockInterface $mock) use ($tenancy) {
                $mock->shouldReceive('where')
                     ->withArgs([
                         'email',
                         'test@email.com',
                     ])
                     ->andReturnSelf()
                     ->once();

                if ($tenancy !== null) {
                    $mock->shouldReceive('where')
                         ->withArgs([
                             'tenancy',
                             'my-tenancy',
                         ])
                         ->andReturnSelf()
                         ->once();
                    $mock->shouldReceive('where')
                         ->withArgs([
                             'tenant_id',
                             '7',
                         ])
                         ->andReturnSelf()
                         ->once();
                }

                $mock->shouldReceive('delete')->andReturn(0)->once();

                $mock->shouldReceive('insert')
                     ->with(Mockery::on(static function ($arg) use ($tenancy) {
                         $check = is_array($arg)
                                  && (isset($arg['email']) && $arg['email'] === 'test@email.com')
                                  && (isset($arg['token']) && is_string($arg['token']) && strlen($arg['token']) === 60)
                                  && (isset($arg['created_at']) && $arg['created_at'] instanceof \Illuminate\Support\Carbon);

                         if ($tenancy !== null) {
                             return $check
                                    && count($arg) === 5
                                    && (isset($arg['tenancy']) && $arg['tenancy'] === 'my-tenancy')
                                    && (isset($arg['tenant_id']) && $arg['tenant_id'] === 7);
                         }

                         return $check
                                && count($arg) === 3;
                     }))
                     ->once();
            });

            $mock->shouldReceive('table')
                 ->withArgs(['my_passwords'])
                 ->andReturn($builder)
                 ->twice();
        });

        $hasher = $this->app->make('hash');

        $repository = new SproutAuthDatabaseTokenRepository(
            $sprout,
            $connection,
            $hasher,
            'my_passwords',
            'hash-key',
            3600,
            60
        );

        $repository->create($user);
    }

    #[Test, DataProvider('tenantContextDataProvider')]
    public function canCheckIfExistingTokensExist(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        /** @var \Illuminate\Foundation\Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout     = $this->getSprout($app, $tenancy, $tenant);
        $user       = $this->mockUser();
        $hasher     = $this->app->make('hash');
        $token      = hash_hmac('sha256', Str::random(40), 'hash-key');
        $connection = $this->mockConnectionGet($tenancy, $token, $hasher);

        $repository = new SproutAuthDatabaseTokenRepository(
            $sprout,
            $connection,
            $hasher,
            'my_passwords',
            'hash-key',
            3600,
            60
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

        $sprout     = $this->getSprout($app, $tenancy, $tenant);
        $user       = $this->mockUser();
        $hasher     = $this->app->make('hash');
        $token      = hash_hmac('sha256', Str::random(40), 'hash-key');
        $connection = $this->mockConnectionGet(
            $tenancy,
            $token,
            $hasher,
            true,
            fn (Carbon $carbon) => $carbon->subMinutes(3700)
        );

        $repository = new SproutAuthDatabaseTokenRepository(
            $sprout,
            $connection,
            $hasher,
            'my_passwords',
            'hash-key',
            3600,
            60
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

        $sprout     = $this->getSprout($app, $tenancy, $tenant);
        $user       = $this->mockUser();
        $hasher     = $this->app->make('hash');
        $token      = hash_hmac('sha256', Str::random(40), 'hash-key');
        $connection = $this->mockConnectionGet(
            $tenancy,
            $token,
            $hasher,
            false
        );

        $repository = new SproutAuthDatabaseTokenRepository(
            $sprout,
            $connection,
            $hasher,
            'my_passwords',
            'hash-key',
            3600,
            60
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

        $sprout     = $this->getSprout($app, $tenancy, $tenant);
        $user       = $this->mockUser();
        $hasher     = $this->app->make('hash');
        $token      = hash_hmac('sha256', Str::random(40), 'hash-key');
        $connection = $this->mockConnectionGet(
            $tenancy,
            $token,
            $hasher,
            true,
            fn (Carbon $carbon) => $carbon->subSeconds(10)
        );

        $repository = new SproutAuthDatabaseTokenRepository(
            $sprout,
            $connection,
            $hasher,
            'my_passwords',
            'hash-key',
            3600,
            60
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

        $sprout     = $this->getSprout($app, $tenancy, $tenant);
        $user       = $this->mockUser();
        $hasher     = $this->app->make('hash');
        $token      = hash_hmac('sha256', Str::random(40), 'hash-key');
        $connection = $this->mockConnectionGet(
            $tenancy,
            $token,
            $hasher,
            true,
            fn (Carbon $carbon) => $carbon->subMinute()
        );

        $repository = new SproutAuthDatabaseTokenRepository(
            $sprout,
            $connection,
            $hasher,
            'my_passwords',
            'hash-key',
            3600,
            60
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

        $sprout     = $this->getSprout($app, $tenancy, $tenant);
        $user       = $this->mockUser();
        $hasher     = $this->app->make('hash');
        $token      = hash_hmac('sha256', Str::random(40), 'hash-key');
        $connection = $this->mockConnectionGet(
            $tenancy,
            $token,
            $hasher,
            true,
            fn (Carbon $carbon) => $carbon->subMinutes(3700)
        );

        $repository = new SproutAuthDatabaseTokenRepository(
            $sprout,
            $connection,
            $hasher,
            'my_passwords',
            'hash-key',
            3600,
            60
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

        $sprout     = $this->getSprout($app, $tenancy, $tenant);
        $user       = $this->mockUser();
        $hasher     = $this->app->make('hash');
        $token      = hash_hmac('sha256', Str::random(40), 'hash-key');
        $connection = $this->mockConnectionGet(
            $tenancy,
            $token,
            $hasher,
            false
        );

        $repository = new SproutAuthDatabaseTokenRepository(
            $sprout,
            $connection,
            $hasher,
            'my_passwords',
            'hash-key',
            3600,
            60
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

        $repository = new SproutAuthDatabaseTokenRepository(
            $sprout,
            Mockery::mock(Connection::class, static function (MockInterface $mock) use ($tenancy) {
                $mock->shouldReceive('table')
                     ->withArgs(['my_passwords'])
                     ->andReturn(Mockery::mock(Builder::class, static function (MockInterface $mock) use ($tenancy) {
                         $mock->shouldReceive('where')
                              ->withArgs([
                                  'email',
                                  'test@email.com',
                              ])
                              ->andReturnSelf()
                              ->once();

                         if ($tenancy !== null) {
                             $mock->shouldReceive('where')
                                  ->withArgs([
                                      'tenancy',
                                      'my-tenancy',
                                  ])
                                  ->andReturnSelf()
                                  ->once();
                             $mock->shouldReceive('where')
                                  ->withArgs([
                                      'tenant_id',
                                      '7',
                                  ])
                                  ->andReturnSelf()
                                  ->once();
                         }

                         $mock->shouldReceive('delete')
                              ->andReturn(1)
                              ->once();
                     }))
                     ->once();
            }),
            $this->app->make('hash'),
            'my_passwords',
            'hash-key',
            3600,
            60
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
            'no tenant context' => [null, null],
            'tenant context'    => [$tenancy, $tenant],
        ];
    }
}
