<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Illuminate\Auth\AuthManager;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Overrides\Auth\SproutAuthCacheTokenRepository;
use Sprout\Overrides\Auth\SproutAuthDatabaseTokenRepository;
use Sprout\Overrides\Auth\SproutAuthPasswordBrokerManager;
use Sprout\Overrides\AuthOverride;
use Sprout\Overrides\CookieOverride;
use Sprout\Support\Services;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use Workbench\App\Models\User;
use function Sprout\sprout;
use function Sprout\tenancy;

class AuthOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(AuthOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'auth' => [
                'driver' => AuthOverride::class,
            ],
        ]);

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('auth'));
        $this->assertSame(AuthOverride::class, $sprout->overrides()->getOverrideClass('auth'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('auth'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('auth'));
    }

    #[Test]
    public function isBootedCorrectly(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $this->assertFalse(app()->isDeferredService('auth.password'));
        $this->assertTrue(app()->bound('auth.password'));
        $this->assertFalse(app()->resolved('auth.password'));
        $this->assertFalse(app()->resolved('auth.password.broker'));
        $this->assertInstanceOf(SproutAuthPasswordBrokerManager::class, app()->make('auth.password'));
        $this->assertTrue(app()->resolved('auth.password'));
        $this->assertFalse(app()->resolved('auth.password.broker'));
    }

    #[Test]
    public function rebindsAuthPassword(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        app()->rebinding('auth.password', function ($app, $passwordBrokerManager) {
            $this->assertInstanceOf(SproutAuthPasswordBrokerManager::class, $passwordBrokerManager);
        });

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);
    }

    #[Test]
    public function forgetsAuthPasswordInstance(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        $this->assertFalse(app()->resolved('auth.password'));
        $this->assertNotInstanceOf(SproutAuthPasswordBrokerManager::class, app()->make('auth.password'));
        $this->assertTrue(app()->resolved('auth.password'));

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $this->assertInstanceOf(SproutAuthPasswordBrokerManager::class, app()->make('auth.password'));
    }

    #[Test]
    public function replacesTheDatabaseTokenRepositoryDriver(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        config()->set('auth.passwords.users.driver', 'database');
        config()->set('auth.passwords.users.table', 'password_reset_tokens');
        config()->set('multitenancy.providers.eloquent.model', TenantModel::class);

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $broker = app()->make('auth.password.broker');

        $this->assertInstanceOf(SproutAuthDatabaseTokenRepository::class, $broker->getRepository());

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = tenancy();

        $tenancy->setTenant($tenant);
        sprout()->setCurrentTenancy($tenancy);

        $user     = User::factory()->createOne();
        $token    = $broker->createToken($user);
        $database = app()->make('db.connection');

        $dbEntry = $database->table('password_reset_tokens')
                            ->where('email', '=', $user->getAttribute('email'))
                            ->where('tenancy', '=', $tenancy->getName())
                            ->where('tenant_id', '=', $tenant->getTenantKey())
                            ->first();

        $this->assertNotNull($dbEntry);
        $this->assertTrue(app()->make('hash')->check($token, $dbEntry->token));
    }

    #[Test]
    public function replacesTheCacheTokenRepositoryDriver(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        config()->set('auth.passwords.users.driver', 'cache');
        config()->set('auth.passwords.users.store', 'array');
        config()->set('multitenancy.providers.eloquent.model', TenantModel::class);

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $broker = app()->make('auth.password.broker');

        $this->assertInstanceOf(SproutAuthCacheTokenRepository::class, $broker->getRepository());

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = tenancy();

        $tenancy->setTenant($tenant);
        sprout()->setCurrentTenancy($tenancy);

        $user  = User::factory()->createOne();
        $token = $broker->createToken($user);
        $cache = app()->make('cache')->store('array');

        $cacheKey = $tenancy->getName() . '.' . $tenant->getTenantResourceKey() . '.' . $user->getAttribute('email');

        $this->assertTrue($cache->has($cacheKey));
        $this->assertSame($token, $cache->get($cacheKey)[0]);
    }

    #[Test]
    public function canFlushBrokers(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        config()->set('auth.passwords.users.driver', 'database');

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        /** @var SproutAuthPasswordBrokerManager $manager */
        $manager = app()->make('auth.password');

        $this->assertFalse($manager->isResolved());

        $manager->broker();

        $this->assertTrue($manager->isResolved());

        $manager->flush();

        $this->assertFalse($manager->isResolved());
    }

    #[Test]
    public function performsSetup(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $override = $sprout->getOverrides()[AuthOverride::class];

        $this->assertInstanceOf(AuthOverride::class, $override);

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $tenancy->setTenant($tenant);

        $this->instance('auth', $this->spy(AuthManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasResolvedGuards')->once()->andReturn(true);
            $mock->shouldReceive('forgetGuards')->once();
        }));

        $this->instance('auth.password', $this->spy(SproutAuthPasswordBrokerManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('flush')->once();
        }));

        $override->setup($tenancy, $tenant);
    }

    #[Test]
    public function performsCleanup(): void
    {
        $this->markTestSkipped('This test needs to be updated');

        $sprout = sprout();

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $override = $sprout->getOverrides()[AuthOverride::class];

        $this->assertInstanceOf(AuthOverride::class, $override);

        $tenant  = TenantModel::factory()->createOne();
        $tenancy = $sprout->tenancies()->get();

        $tenancy->setTenant($tenant);

        $this->instance('auth', $this->spy(AuthManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasResolvedGuards')->once()->andReturn(true);
            $mock->shouldReceive('forgetGuards')->once();
        }));

        $this->instance('auth.password', $this->spy(SproutAuthPasswordBrokerManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('flush')->once();
        }));

        $override->cleanup($tenancy, $tenant);
    }
}
