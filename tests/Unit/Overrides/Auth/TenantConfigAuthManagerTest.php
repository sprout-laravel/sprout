<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides\Auth;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Overrides\Auth\TenantConfigAuthManager;
use Sprout\Tests\Unit\UnitTestCase;

class TenantConfigAuthManagerTest extends UnitTestCase
{
    #[Test]
    public function canBeSyncedFromOriginal(): void
    {
        $original = Mockery::mock(AuthManager::class);

        $app = Mockery::mock(Application::class);

        $sprout = new TenantConfigAuthManager($app, $original);

        $this->assertTrue($sprout->wasSyncedFromOriginal());
    }

    #[Test]
    public function syncsRegistrationsFromOriginal(): void
    {
        $original = new AuthManager($this->app);

        // Seed every property syncOriginal() carries across.
        $original->extend('original-driver', static fn () => Mockery::mock(Guard::class));
        $original->provider('original-provider', static fn () => Mockery::mock(UserProvider::class));

        $resolver = static fn () => null;
        $original->resolveUsersUsing($resolver);

        // guards is a resolved-instance cache with no public setter.
        $guard = Mockery::mock(Guard::class);
        (new \ReflectionProperty($original, 'guards'))->setValue($original, ['original-guard' => $guard]);

        $manager = new TenantConfigAuthManager($this->app, $original);

        $read = fn (string $property) => (new \ReflectionProperty($manager, $property))->getValue($manager);

        $this->assertTrue($manager->wasSyncedFromOriginal());
        $this->assertArrayHasKey('original-driver', $read('customCreators'));
        $this->assertArrayHasKey('original-provider', $read('customProviderCreators'));
        $this->assertArrayHasKey('original-guard', $read('guards'));
        $this->assertSame($guard, $read('guards')['original-guard']);
        $this->assertSame($resolver, $read('userResolver'));
    }

    #[Test]
    public function addsNameWhenResolvingDriver(): void
    {
        $app = Mockery::mock(Application::class, static function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('offsetGet')
                 ->with('config')
                 ->andReturn(
                     Mockery::mock(Repository::class, static function (Mockery\MockInterface $mock) {
                         $mock->shouldReceive('offsetGet')
                              ->with('auth.providers.fake-provider')
                              ->andReturn([
                                  'driver' => 'fake',
                              ])
                              ->once();
                     }),
                 )
                 ->once();
        });

        $sprout = new TenantConfigAuthManager($app);

        $sprout->provider('fake', function (Application $app, array $config) {
            $this->assertArrayHasKey('provider', $config);
            $this->assertSame('fake-provider', $config['provider']);

            return Mockery::mock(UserProvider::class);
        });

        $sprout->createUserProvider('fake-provider');
    }

    #[Test]
    public function throwsAnExceptionWhenTheresNoDriverInTheConfig(): void
    {
        $app = Mockery::mock(Application::class, static function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('offsetGet')
                 ->with('config')
                 ->andReturn(
                     Mockery::mock(Repository::class, static function (Mockery\MockInterface $mock) {
                         $mock->shouldReceive('offsetGet')
                              ->with('auth.providers.fake-provider')
                              ->andReturn([
                                  'name' => 'hi',
                              ])
                              ->once();
                     }),
                 )
                 ->once();
        });

        $sprout = new TenantConfigAuthManager($app);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Authentication user provider driver must be provided.');

        $sprout->createUserProvider('fake-provider');
    }

    #[Test]
    public function returnsNullWhenTheresNoConfig(): void
    {
        $app = Mockery::mock(Application::class, static function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('offsetGet')
                 ->with('config')
                 ->andReturn(
                     Mockery::mock(Repository::class, static function (Mockery\MockInterface $mock) {
                         $mock->shouldReceive('offsetGet')
                              ->with('auth.providers.fake-provider')
                              ->andReturn(null)
                              ->once();
                     }),
                 )
                 ->once();
        });

        $sprout = new TenantConfigAuthManager($app);

        $this->assertNull($sprout->createUserProvider('fake-provider'));
    }

    #[Test]
    public function canCreateProvidersNormally(): void
    {
        $app = Mockery::mock($this->app, static function (Mockery\MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new TenantConfigAuthManager($app);

        $this->assertInstanceOf(EloquentUserProvider::class, $sprout->createUserProvider('users'));
    }

    #[Test]
    public function throwsAnExceptionForDriversThatDoNotExist(): void
    {
        $app = Mockery::mock($this->app, static function (Mockery\MockInterface $mock) {
            $mock->makePartial();
        });

        $app['config']['auth.providers.fake.driver'] = 'fake';

        $sprout = new TenantConfigAuthManager($app);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Authentication user provider [fake] is not defined.');

        $sprout->createUserProvider('fake');
    }
}
