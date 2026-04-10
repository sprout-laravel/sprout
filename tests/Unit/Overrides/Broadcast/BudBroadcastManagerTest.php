<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Overrides\Broadcast;

use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Bud\Overrides\Broadcast\BudBroadcastManager;
use Sprout\Bud\Tests\Unit\UnitTestCase;

class BudBroadcastManagerTest extends UnitTestCase
{
    #[Test]
    public function canBeSyncedFromOriginal(): void
    {
        $original = Mockery::mock(BroadcastManager::class);

        $app = Mockery::mock(Application::class);

        $sprout = new BudBroadcastManager($app, $original);

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
                              ->with('broadcasting.connections.fake-connection')
                              ->andReturn([
                                  'driver' => 'fake',
                              ])
                              ->once();
                     })
                 )
                 ->once();
        });

        $sprout = new BudBroadcastManager($app);

        $sprout->extend('fake', function (Application $app, array $config) {
            $this->assertArrayHasKey('name', $config);
            $this->assertSame('fake-connection', $config['name']);

            return Mockery::mock(Broadcaster::class);
        });

        $sprout->connection('fake-connection');
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
                              ->with('broadcasting.connections.fake-connection')
                              ->andReturn([
                                  'name' => 'hi',
                              ])
                              ->once();
                     })
                 )
                 ->once();
        });

        $sprout = new BudBroadcastManager($app);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Broadcast connection [fake-connection] does not have a configured driver.');

        $sprout->connection('fake-connection');
    }

    #[Test]
    public function throwsAnExceptionWhenTheresNoConfig(): void
    {
        $app = Mockery::mock(Application::class, static function (Mockery\MockInterface $mock) {
            $mock->shouldReceive('offsetGet')
                 ->with('config')
                 ->andReturn(
                     Mockery::mock(Repository::class, static function (Mockery\MockInterface $mock) {
                         $mock->shouldReceive('offsetGet')
                              ->with('broadcasting.connections.fake-connection')
                              ->andReturn(null)
                              ->once();
                     })
                 )
                 ->once();
        });

        $sprout = new BudBroadcastManager($app);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Broadcast connection [fake-connection] is not defined.');

        $sprout->connection('fake-connection');
    }

    #[Test]
    public function canCreateConnectionsNormally(): void
    {
        $app = Mockery::mock($this->app, static function (Mockery\MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new BudBroadcastManager($app);

        $this->assertInstanceOf(NullBroadcaster::class, $sprout->connection('null'));
    }

    #[Test]
    public function throwsAnExceptionForDriversThatDoNotExist(): void
    {
        $app = Mockery::mock($this->app, static function (Mockery\MockInterface $mock) {
            $mock->makePartial();
        });

        $app['config']['broadcasting.connections.fake.driver'] = 'fake';

        $sprout = new BudBroadcastManager($app);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [fake] is not supported.');

        $sprout->connection('fake');
    }
}
