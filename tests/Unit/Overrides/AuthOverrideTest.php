<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Overrides\Auth\TenantAwareCacheTokenRepository;
use Sprout\Overrides\Auth\TenantAwareDatabaseTokenRepository;
use Sprout\Overrides\Auth\TenantAwarePasswordBrokerManager;
use Sprout\Overrides\AuthOverride;
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
            $config->set('sprout.services', []);
        });
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(AuthOverride::class, BootableServiceOverride::class));
        $this->assertFalse(is_subclass_of(AuthOverride::class, DeferrableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $this->assertTrue($sprout->hasRegisteredOverride(AuthOverride::class));
        $this->assertTrue($sprout->isBootableOverride(AuthOverride::class));
        $this->assertFalse($sprout->isDeferrableOverride(AuthOverride::class));
    }

    #[Test]
    public function isBootedCorrectly(): void
    {
        $sprout = sprout();

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $this->assertFalse(app()->isDeferredService('auth.password'));
        $this->assertTrue(app()->bound('auth.password'));
        $this->assertFalse(app()->resolved('auth.password'));
        $this->assertFalse(app()->resolved('auth.password.broker'));
        $this->assertInstanceOf(TenantAwarePasswordBrokerManager::class, app()->make('auth.password'));
        $this->assertTrue(app()->resolved('auth.password'));
        $this->assertFalse(app()->resolved('auth.password.broker'));
    }

    #[Test]
    public function rebindsAuthPassword(): void
    {
        $sprout = sprout();

        app()->rebinding('auth.password', function ($app, $passwordBrokerManager) {
            $this->assertInstanceOf(TenantAwarePasswordBrokerManager::class, $passwordBrokerManager);
        });

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);
    }

    #[Test]
    public function forgetsAuthPasswordInstance(): void
    {
        $sprout = sprout();

        $this->assertFalse(app()->resolved('auth.password'));
        $this->assertNotInstanceOf(TenantAwarePasswordBrokerManager::class, app()->make('auth.password'));
        $this->assertTrue(app()->resolved('auth.password'));

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $this->assertInstanceOf(TenantAwarePasswordBrokerManager::class, app()->make('auth.password'));
    }

    #[Test]
    public function replacesTheDatabaseTokenRepositoryDriver(): void
    {
        $sprout = sprout();

        config()->set('auth.passwords.users.driver', 'database');
        config()->set('auth.passwords.users.table', 'password_reset_tokens');
        config()->set('multitenancy.providers.eloquent.model', TenantModel::class);

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $broker = app()->make('auth.password.broker');

        $this->assertInstanceOf(TenantAwareDatabaseTokenRepository::class, $broker->getRepository());

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
        $sprout = sprout();

        config()->set('auth.passwords.users.driver', 'cache');
        config()->set('auth.passwords.users.store', 'array');
        config()->set('multitenancy.providers.eloquent.model', TenantModel::class);

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        $broker = app()->make('auth.password.broker');

        $this->assertInstanceOf(TenantAwareCacheTokenRepository::class, $broker->getRepository());

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
        $sprout = sprout();

        config()->set('auth.passwords.users.driver', 'database');

        $sprout->registerOverride(Services::AUTH, AuthOverride::class);

        /** @var TenantAwarePasswordBrokerManager $manager */
        $manager = app()->make('auth.password');

        $this->assertFalse($manager->isResolved());

        $manager->broker();

        $this->assertTrue($manager->isResolved());

        $manager->flush();

        $this->assertFalse($manager->isResolved());
    }
}
