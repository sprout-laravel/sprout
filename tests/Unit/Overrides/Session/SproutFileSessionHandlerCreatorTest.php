<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Overrides\Session;

use Illuminate\Config\Repository;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Overrides\Session\SproutFileSessionHandler;
use Sprout\Core\Overrides\Session\SproutFileSessionHandlerCreator;
use Sprout\Core\Sprout;
use Sprout\Core\Tests\Unit\UnitTestCase;
use function Sprout\Core\sprout;

class SproutFileSessionHandlerCreatorTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    #[Test]
    public function canCreateTheFileHandler(): void
    {
        $creator = new SproutFileSessionHandlerCreator(
            $this->app,
            $this->app->make(Sprout::class)
        );

        $handler = $creator();

        $this->assertInstanceOf(SproutFileSessionHandler::class, $handler);
        $this->assertFalse($handler->hasTenancy());
        $this->assertFalse($handler->hasTenant());

        // Assert that the path is the default value
        $defaultPath = rtrim(config('session.files'), '/');
        $this->assertEquals($defaultPath, $handler->getPath());
    }

    #[Test]
    public function canCreateTheFileHandlerWithTenantContext(): void
    {
        $tenancy = Mockery::mock(Tenancy::class);
        $tenant  = Mockery::mock(Tenant::class)->makePartial();
        $sprout  = sprout();

        $tenancy->shouldReceive('tenant')->andReturn($tenant)->once();
        $tenant->shouldReceive('getTenantResourceKey')->andReturn('tenant-resource-key')->once();

        $sprout->setCurrentTenancy($tenancy);

        $creator = new SproutFileSessionHandlerCreator(
            $this->app,
            $this->app->make(Sprout::class)
        );

        $handler = $creator();

        $this->assertInstanceOf(SproutFileSessionHandler::class, $handler);
        $this->assertTrue($handler->hasTenancy());
        $this->assertTrue($handler->hasTenant());
        $this->assertSame($tenancy, $handler->getTenancy());
        $this->assertSame($tenant, $handler->getTenant());

        // Assert that the path is not the default value
        $defaultPath = rtrim(config('session.files'), '/');
        $handlerPath = $handler->getPath();

        $this->assertNotEquals($defaultPath, $handlerPath);
        $this->assertEquals($defaultPath . DIRECTORY_SEPARATOR . 'tenant-resource-key', $handlerPath);
    }
}
