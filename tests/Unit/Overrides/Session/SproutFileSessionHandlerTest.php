<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides\Session;

use Carbon\Carbon;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\Session\SproutFileSessionHandler;
use Sprout\Tests\Unit\UnitTestCase;

class SproutFileSessionHandlerTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    protected function createHandler(?Tenancy $tenancy = null, ?Tenant $tenant = null, ?Filesystem $files = null): SproutFileSessionHandler
    {
        $defaultPath = '/default/path';
        $lifetime    = config('session.lifetime');
        $files       ??= Mockery::mock(Filesystem::class);

        $handler = new SproutFileSessionHandler($files, $defaultPath, $lifetime);

        if ($tenancy && $tenant) {
            $handler->setTenancy($tenancy)->setTenant($tenant);
        }

        return $handler;
    }

    #[Test]
    public function hasTheCorrectPathAndTenancyState(): void
    {
        $handler = $this->createHandler();

        $this->assertInstanceOf(SproutFileSessionHandler::class, $handler);
        $this->assertFalse($handler->hasTenancy());
        $this->assertFalse($handler->hasTenant());
        $this->assertEquals('/default/path', $handler->getPath());
    }

    #[Test]
    public function canCreateTheFileHandlerWithTenantContext(): void
    {
        $tenancy = Mockery::mock(Tenancy::class);
        $tenant  = Mockery::mock(Tenant::class)->makePartial();

        $tenant->shouldReceive('getTenantResourceKey')->andReturn('tenant-resource-key')->once();

        $handler = $this->createHandler($tenancy, $tenant);

        $this->assertInstanceOf(SproutFileSessionHandler::class, $handler);
        $this->assertTrue($handler->hasTenancy());
        $this->assertTrue($handler->hasTenant());
        $this->assertSame($tenancy, $handler->getTenancy());
        $this->assertSame($tenant, $handler->getTenant());
        $this->assertEquals('/default/path' . DIRECTORY_SEPARATOR . 'tenant-resource-key', $handler->getPath());
    }

    #[Test, DataProvider('fileSessionDataProvider')]
    public function canReadFromValidSession(?Tenancy $tenancy, ?Tenant $tenant, string $expectedPath): void
    {
        $sessionId = 'my-session-id';

        $handler = $this->createHandler($tenancy, $tenant, Mockery::mock(Filesystem::class, function (Mockery\MockInterface $mock) use ($expectedPath, $sessionId) {
            $mock->shouldReceive('isFile')->withArgs([$expectedPath . DIRECTORY_SEPARATOR . $sessionId])->andReturn(true)->once();
            $mock->shouldReceive('lastModified')->withArgs([$expectedPath . DIRECTORY_SEPARATOR . $sessionId])->andReturn(Carbon::now()->subHour()->timestamp)->once();
            $mock->shouldReceive('sharedGet')->withArgs([$expectedPath . DIRECTORY_SEPARATOR . $sessionId])->andReturn('my-session-data')->once();
        }))->setTenancy($tenancy)->setTenant($tenant);

        $this->assertSame('my-session-data', $handler->read($sessionId));
    }

    #[Test, DataProvider('fileSessionDataProvider')]
    public function doesNotReadFromFilesystemWhenSessionIsInvalidOrTooOld(?Tenancy $tenancy, ?Tenant $tenant, string $expectedPath): void
    {
        $sessionId = 'my-session-id';

        $handler = $this->createHandler($tenancy, $tenant, Mockery::mock(Filesystem::class, function (Mockery\MockInterface $mock) use ($expectedPath, $sessionId) {
            $mock->shouldReceive('isFile')->withArgs([$expectedPath . DIRECTORY_SEPARATOR . $sessionId])->andReturn(false)->once();
        }))->setTenancy($tenancy)->setTenant($tenant);

        $this->assertEmpty($handler->read($sessionId));
    }

    #[Test, DataProvider('fileSessionDataProvider')]
    public function canWriteSessionData(?Tenancy $tenancy, ?Tenant $tenant, string $expectedPath): void
    {
        $sessionId = 'my-session-id';

        $handler = $this->createHandler($tenancy, $tenant, Mockery::mock(Filesystem::class, function (Mockery\MockInterface $mock) use ($expectedPath, $sessionId) {
            $mock->shouldReceive('put')->withArgs([$expectedPath . DIRECTORY_SEPARATOR . $sessionId, 'my-session-data', true])->once();
        }))->setTenancy($tenancy)->setTenant($tenant);

        $this->assertTrue($handler->write($sessionId, 'my-session-data'));
    }

    #[Test, DataProvider('fileSessionDataProvider')]
    public function canDestroySessionData($tenancy, $tenant, $expectedPath): void
    {
        $sessionId = 'my-session-id';

        $handler = $this->createHandler($tenancy, $tenant, Mockery::mock(Filesystem::class, function (Mockery\MockInterface $mock) use ($expectedPath, $sessionId) {
            $mock->shouldReceive('delete')->withArgs([$expectedPath . DIRECTORY_SEPARATOR . $sessionId])->once();
        }))->setTenancy($tenancy)->setTenant($tenant);

        $this->assertTrue($handler->destroy($sessionId));
    }

    public static function fileSessionDataProvider(): array
    {
        $defaultPath = '/default/path';
        $tenancy     = Mockery::mock(Tenancy::class);
        $tenant      = Mockery::mock(Tenant::class)->makePartial();
        $tenant->shouldReceive('getTenantResourceKey')->andReturn('tenant-resource-key');

        return [
            'outside of tenant context' => [null, null, $defaultPath],
            'inside of tenant context' => [$tenancy, $tenant, $defaultPath . DIRECTORY_SEPARATOR . 'tenant-resource-key'],
        ];
    }
}
