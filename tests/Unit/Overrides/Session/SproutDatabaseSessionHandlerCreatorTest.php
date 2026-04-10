<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Overrides\Session;

use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Overrides\Session\SproutDatabaseSessionHandler;
use Sprout\Core\Overrides\Session\SproutDatabaseSessionHandlerCreator;
use Sprout\Core\Sprout;
use Sprout\Core\Support\SettingsRepository;
use Sprout\Core\Tests\Unit\UnitTestCase;

class SproutDatabaseSessionHandlerCreatorTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    #[Test]
    public function canCreateTheDatabaseHandler(): void
    {
        $connection = Mockery::mock(Connection::class);
        $this->swap('db', Mockery::mock(DatabaseManager::class, static function (Mockery\MockInterface $mock) use ($connection) {
            $mock->shouldReceive('connection')->with(null)->andReturn($connection)->once();
        }));

        /** @var Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $creator = new SproutDatabaseSessionHandlerCreator(
            $this->app,
            $sprout
        );

        $handler = $creator();

        $this->assertInstanceOf(SproutDatabaseSessionHandler::class, $handler);
        $this->assertFalse($handler->hasTenancy());
        $this->assertFalse($handler->hasTenant());
    }

    #[Test]
    public function canCreateTheDatabaseHandlerWithTenantContext(): void
    {
        $tenancy = Mockery::mock(Tenancy::class);
        $tenant  = Mockery::mock(Tenant::class)->makePartial();

        /** @var Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $tenancy->shouldReceive('tenant')->andReturn($tenant)->once();

        $sprout->setCurrentTenancy($tenancy);

        $connection = Mockery::mock(Connection::class);
        $this->swap('db', Mockery::mock(DatabaseManager::class, static function (Mockery\MockInterface $mock) use ($connection) {
            $mock->shouldReceive('connection')->with(null)->andReturn($connection)->once();
        }));

        $creator = new SproutDatabaseSessionHandlerCreator(
            $this->app,
            $sprout
        );

        $handler = $creator();

        $this->assertInstanceOf(SproutDatabaseSessionHandler::class, $handler);
        $this->assertTrue($handler->hasTenancy());
        $this->assertTrue($handler->hasTenant());
        $this->assertSame($tenancy, $handler->getTenancy());
        $this->assertSame($tenant, $handler->getTenant());
    }

    #[Test]
    public function canCreateTheDatabaseHandlerWithTenantContextButNoTenancy(): void
    {
        $connection = Mockery::mock(Connection::class);
        $this->swap('db', Mockery::mock(DatabaseManager::class, static function (Mockery\MockInterface $mock) use ($connection) {
            $mock->shouldReceive('connection')->with(null)->andReturn($connection)->once();
        }));

        /** @var Application&MockInterface $app */
        $app = Mockery::mock($this->app, static function (MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $sprout->markAsInContext();

        $this->assertFalse($sprout->hasCurrentTenancy());

        $creator = new SproutDatabaseSessionHandlerCreator(
            $this->app,
            $sprout
        );

        $handler = $creator();

        $this->assertInstanceOf(SproutDatabaseSessionHandler::class, $handler);
        $this->assertFalse($handler->hasTenancy());
        $this->assertFalse($handler->hasTenant());
    }
}
