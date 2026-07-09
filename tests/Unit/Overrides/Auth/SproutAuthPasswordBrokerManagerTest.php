<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides\Auth;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Overrides\Auth\SproutAuthCacheTokenRepository;
use Sprout\Overrides\Auth\SproutAuthDatabaseTokenRepository;
use Sprout\Overrides\Auth\SproutAuthPasswordBrokerManager;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;

class SproutAuthPasswordBrokerManagerTest extends UnitTestCase
{
    public static function expireAndThrottleDataProvider(): array
    {
        return [
            [15, 20],
            [],
            [60, 60],
            [null, 11],
            [26, null],
            [0, null],
        ];
    }

    #[Test, DataProvider('expireAndThrottleDataProvider')]
    public function createsSproutDatabaseTokenRepository(?int $expire = null, ?int $throttle = null): void
    {
        /** @var Application&MockInterface $app */
        $app = $this->mockApp('database', $expire, $throttle);

        $app->shouldReceive('make')
            ->withArgs(['db'])
            ->andReturn(
                Mockery::mock(DatabaseManager::class, static function (MockInterface $mock) {
                    $mock->shouldReceive('connection')
                         ->andReturn(Mockery::mock(Connection::class))
                         ->once();
                }),
            )
            ->once();

        $sprout  = new Sprout($app, new SettingsRepository());
        $manager = new SproutAuthPasswordBrokerManager($app, $sprout);

        $this->assertFalse($manager->isResolved());

        $repository = $manager->broker('my-passwords')->getRepository();

        $expectedExpire = ($expire === 0 || $expire === null ? 60 : $expire) * 60; // Default to 60 minutes if expire is null or 0

        $this->assertInstanceOf(SproutAuthDatabaseTokenRepository::class, $repository);
        $this->assertTrue($manager->isResolved('my-passwords'));
        $this->assertFalse($manager->isResolved());
        $this->assertSame($expectedExpire, $repository->getExpires());
        $this->assertSame($throttle ?? 0, $repository->getThrottle());
    }

    #[Test, DataProvider('expireAndThrottleDataProvider')]
    public function createsSproutCacheTokenRepository(?int $expire = null, ?int $throttle = null): void
    {
        /** @var Application&MockInterface $app */
        $app = $this->mockApp('cache', $expire, $throttle);

        $app->shouldReceive('make')
            ->withArgs(['cache'])
            ->andReturn(
                Mockery::mock(CacheRepository::class, static function (MockInterface $mock) {
                    $mock->shouldReceive('store')
                         ->withArgs(['my-store'])
                         ->andReturn(Mockery::mock(CacheRepository::class))
                         ->once();
                }),
            )
            ->once();

        $sprout = new Sprout($app, new SettingsRepository());

        $manager = new SproutAuthPasswordBrokerManager($app, $sprout);

        $this->assertFalse($manager->isResolved());

        $repository = $manager->broker('my-passwords')->getRepository();

        $expectedExpire = ($expire === 0 || $expire === null ? 60 : $expire) * 60; // Default to 60 minutes if expire is null or 0

        $this->assertInstanceOf(SproutAuthCacheTokenRepository::class, $repository);
        $this->assertTrue($manager->isResolved('my-passwords'));
        $this->assertFalse($manager->isResolved());
        $this->assertSame($expectedExpire, $repository->getExpires());
        $this->assertSame($throttle ?? 0, $repository->getThrottle());
        $this->assertSame('my-prefix', $repository->getPrefix());
    }

    #[Test]
    public function fallsBackToDatabaseIfDriverIsSomethingElse(): void
    {
        /** @var Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $app->make('config')->set('auth.passwords.users.driver', 'this-is-a-fake-driver');

        $sprout = new Sprout($app, new SettingsRepository());

        $manager = new SproutAuthPasswordBrokerManager($app, $sprout);

        $this->assertFalse($manager->isResolved());

        $repository = $manager->broker()->getRepository();

        $this->assertNotInstanceOf(SproutAuthCacheTokenRepository::class, $repository);
        $this->assertInstanceOf(SproutAuthDatabaseTokenRepository::class, $repository);

        $this->assertTrue($manager->isResolved());
    }

    #[Test]
    public function canBeFlushed(): void
    {
        /** @var Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $manager = new SproutAuthPasswordBrokerManager($app, $sprout);

        $this->assertFalse($manager->isResolved());

        $manager->broker();

        $this->assertTrue($manager->isResolved());

        $manager->flush();

        $this->assertFalse($manager->isResolved());
    }

    private function mockApp(string $driver, ?int $expire = null, ?int $throttle = null): Application&MockInterface
    {
        $config = [
            'driver'   => $driver,
            'provider' => 'users',
            'expire'   => $expire,
            'throttle' => $throttle,
        ];

        if ($driver === 'database') {
            $config['table'] = 'password_resets';
        }

        if ($driver === 'cache') {
            $config['store']  = 'my-store';
            $config['prefix'] = 'my-prefix';
        }

        /** @var Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) use ($config) {
            $mock->makePartial();

            $repository = Mockery::mock(Repository::class, static function (MockInterface $mock) use ($config) {
                $mock->shouldReceive('get')
                     ->withArgs(['app.key'])
                     ->andReturn('base64:' . base64_encode('fake-key'))
                     ->once();

                // Laravel's PasswordBrokerManager::resolve() reads this to
                // populate the PasswordBroker $timeboxDuration constructor arg.
                $mock->shouldReceive('get')
                     ->withArgs(['auth.timebox_duration', 200000])
                     ->andReturn(200000)
                     ->once();

                $mock->shouldReceive('offsetGet')
                     ->withArgs(['auth.defaults.passwords'])
                     ->andReturn('users')
                     ->atLeast()
                     ->once();

                $mock->shouldReceive('offsetGet')
                     ->withArgs(['auth.passwords.my-passwords'])
                     ->once()
                     ->andReturn($config);
            });

            $mock->shouldReceive('offsetGet')
                 ->withArgs(['config'])
                 ->atLeast()
                 ->once()
                 ->andReturn($repository);

            $mock->shouldReceive('make')
                 ->withArgs(['config'])
                 ->once()
                 ->andReturn($repository);

            $mock->shouldReceive('make')
                 ->withArgs(['hash'])
                 ->andReturn(Mockery::mock(Hasher::class))
                 ->once();
        });

        return $app;
    }
}
