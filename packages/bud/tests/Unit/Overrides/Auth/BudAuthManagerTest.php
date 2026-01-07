<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Overrides\Auth;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Bud\Overrides\Auth\BudAuthManager;
use Sprout\Bud\Overrides\Broadcast\BudBroadcastManager;
use Sprout\Bud\Tests\Unit\UnitTestCase;

class BudAuthManagerTest extends UnitTestCase
{
    #[Test]
    public function canBeSyncedFromOriginal(): void
    {
        $original = Mockery::mock(AuthManager::class);

        $app = Mockery::mock(Application::class);

        $sprout = new BudAuthManager($app, $original);

        $this->assertTrue($sprout->wasSyncedFromOriginal());
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
                     })
                 )
                 ->once();
        });

        $sprout = new BudAuthManager($app);

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
                     })
                 )
                 ->once();
        });

        $sprout = new BudAuthManager($app);

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
                     })
                 )
                 ->once();
        });

        $sprout = new BudAuthManager($app);

        $this->assertNull($sprout->createUserProvider('fake-provider'));
    }

    #[Test]
    public function canCreateProvidersNormally(): void
    {
        $app = Mockery::mock($this->app, static function (Mockery\MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new BudAuthManager($app);

        $this->assertInstanceOf(EloquentUserProvider::class, $sprout->createUserProvider('users'));
    }

    #[Test]
    public function throwsAnExceptionForDriversThatDoNotExist(): void
    {
        $app = Mockery::mock($this->app, static function (Mockery\MockInterface $mock) {
            $mock->makePartial();
        });

        $app['config']['auth.providers.fake.driver'] = 'fake';

        $sprout = new BudAuthManager($app);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Authentication user provider [fake] is not defined.');

        $sprout->createUserProvider('fake');
    }
}
