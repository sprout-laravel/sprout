<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides\Session;

use Carbon\Carbon;
use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\Session\SproutDatabaseSessionHandler;
use Sprout\Tests\Unit\UnitTestCase;

class SproutDatabaseSessionHandlerTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    protected function createHandler(?Tenancy $tenancy = null, ?Tenant $tenant = null, ?Builder $builder = null): SproutDatabaseSessionHandler
    {
        $lifetime   = config('session.lifetime');
        $connection = Mockery::mock(Connection::class, static function (Mockery\MockInterface $mock) use ($builder) {
            if ($builder !== null) {
                $mock->shouldReceive('table')->withArgs(['my_tenant_table'])->andReturn($builder)->atLeast()->once();
            }
        });

        $handler = new SproutDatabaseSessionHandler(
            $connection,
            'my_tenant_table',
            $lifetime
        );

        if ($tenancy && $tenant) {
            $handler->setTenancy($tenancy)->setTenant($tenant);
        }

        return $handler;
    }

    protected function mockTenancyAndTenant(?Tenancy $tenancy = null, ?Tenant $tenant = null): void
    {
        if ($tenancy !== null && $tenant !== null) {
            /**
             * @var \Mockery\MockInterface|Tenancy $tenancy
             * @var \Mockery\MockInterface|Tenant  $tenant
             */
            $tenancy->shouldReceive('getName')->atLeast()->once()->andReturn('my-tenancy');
            $tenant->shouldReceive('getTenantKey')->atLeast()->once()->andReturn(777);
        }
    }

    private function mockQuery($sessionId, ?Tenancy $tenancy, ?Tenant $tenant, $returnValue, bool $find = true): Mockery\MockInterface&Builder
    {
        return Mockery::mock(Builder::class, static function (Mockery\MockInterface $mock) use ($sessionId, $tenancy, $tenant, $returnValue, $find) {
            if ($tenancy !== null) {
                $mock->shouldReceive('where')->withArgs(['tenancy', '=', $tenancy->getName()])->andReturnSelf()->once();
                $mock->shouldReceive('where')->withArgs(['tenant_id', '=', $tenant->getTenantKey()])->andReturnSelf()->once();
            }

            if ($find) {
                $mock->shouldReceive('find')->withArgs([$sessionId])->andReturn($returnValue)->once();
            }
        });
    }

    #[Test]
    public function hasTheCorrectTenancyState(): void
    {
        $handler = $this->createHandler();

        $this->assertInstanceOf(SproutDatabaseSessionHandler::class, $handler);
        $this->assertFalse($handler->hasTenancy());
        $this->assertFalse($handler->hasTenant());
    }

    #[Test]
    public function canCreateTheDatabaseHandlerWithTenantContext(): void
    {
        $tenancy = Mockery::mock(Tenancy::class);
        $tenant  = Mockery::mock(Tenant::class)->makePartial();

        $handler = $this->createHandler($tenancy, $tenant);

        $this->assertInstanceOf(SproutDatabaseSessionHandler::class, $handler);
        $this->assertTrue($handler->hasTenancy());
        $this->assertTrue($handler->hasTenant());
        $this->assertSame($tenancy, $handler->getTenancy());
        $this->assertSame($tenant, $handler->getTenant());
    }

    #[Test, DataProvider('databaseSessionDataProvider')]
    public function canReadFromValidSession(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        $sessionId = 'my-session-id';

        $this->mockTenancyAndTenant($tenancy, $tenant);

        $handler = $this->createHandler(
            $tenancy,
            $tenant,
            $this->mockQuery($sessionId, $tenancy, $tenant, (object)['payload' => base64_encode('my-session-data')])
        )->setTenancy($tenancy)->setTenant($tenant);

        $this->assertSame('my-session-data', $handler->read($sessionId));
    }

    #[Test, DataProvider('databaseSessionDataProvider')]
    public function doesNotReadFromFilesystemWhenSessionIsInvalid(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        $sessionId = 'my-session-id';

        $this->mockTenancyAndTenant($tenancy, $tenant);

        $handler = $this->createHandler(
            $tenancy,
            $tenant,
            $this->mockQuery($sessionId, $tenancy, $tenant, null)
        )->setTenancy($tenancy)->setTenant($tenant);

        $this->assertEmpty($handler->read($sessionId));
    }

    #[Test, DataProvider('databaseSessionDataProvider')]
    public function doesNotReadFromFilesystemWhenSessionHasExpired(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        $sessionId = 'my-session-id';

        $this->mockTenancyAndTenant($tenancy, $tenant);

        $handler = $this->createHandler(
            $tenancy,
            $tenant,
            $this->mockQuery($sessionId, $tenancy, $tenant, (object)[
                'payload'       => base64_encode('my-session-data'),
                'last_activity' => Carbon::now()->subHours(10)->getTimestamp(),
            ])
        )->setTenancy($tenancy)->setTenant($tenant);

        $this->assertEmpty($handler->read($sessionId));
    }

    #[Test, DataProvider('databaseSessionDataProvider')]
    public function canWriteNewSessionData(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        $sessionId = 'my-session-id';

        $this->mockTenancyAndTenant($tenancy, $tenant);

        $query = $this->mockQuery($sessionId, $tenancy, $tenant, null);

        if ($tenancy !== null) {
            $query->shouldReceive('insert')->withArgs([
                [
                    'id'            => $sessionId,
                    'payload'       => base64_encode('my-session-data'),
                    'last_activity' => Carbon::now()->getTimestamp(),
                    'tenancy'       => 'my-tenancy',
                    'tenant_id'     => 777,
                ],
            ])->once();
        } else {
            $query->shouldReceive('insert')->withArgs([
                [
                    'id'            => $sessionId,
                    'payload'       => base64_encode('my-session-data'),
                    'last_activity' => Carbon::now()->getTimestamp(),
                ],
            ])->once();
        }

        $handler = $this->createHandler(
            $tenancy,
            $tenant,
            $query
        )->setTenancy($tenancy)->setTenant($tenant);

        $handler->write($sessionId, 'my-session-data');
    }

    #[Test, DataProvider('databaseSessionDataProvider')]
    public function canWriteExistingSessionData(?Tenancy $tenancy, ?Tenant $tenant): void
    {
        $sessionId = 'my-session-id';

        $this->mockTenancyAndTenant($tenancy, $tenant);

        $query = $this->mockQuery($sessionId, $tenancy, $tenant, (object)['payload' => base64_encode('my-session-data')]);

        $query->shouldReceive('where')->withArgs(['id', $sessionId])->andReturnSelf()->once();

        if ($tenancy !== null) {
            $query->shouldReceive('update')->withArgs([
                [
                    'payload'       => base64_encode('my-session-data'),
                    'last_activity' => Carbon::now()->getTimestamp(),
                ],
            ])->andReturn(1)->once();
        } else {
            $query->shouldReceive('update')->withArgs([
                [
                    'payload'       => base64_encode('my-session-data'),
                    'last_activity' => Carbon::now()->getTimestamp(),
                ],
            ])->andReturn(1)->once();
        }

        $handler = $this->createHandler(
            $tenancy,
            $tenant,
            $query
        )->setTenancy($tenancy)->setTenant($tenant);

        $handler->write($sessionId, 'my-session-data');
    }

    #[Test, DataProvider('databaseSessionDataProvider')]
    public function canDestroySessionData($tenancy, $tenant): void
    {
        $sessionId = 'my-session-id';

        $this->mockTenancyAndTenant($tenancy, $tenant);

        $query = $this->mockQuery($sessionId, $tenancy, $tenant, (object)['payload' => base64_encode('my-session-data')], false);

        $query->shouldNotReceive('find');
        $query->shouldReceive('where')->withArgs(['id', $sessionId])->andReturnSelf()->once();

        if ($tenancy !== null) {
            $query->shouldReceive('delete')->andReturn(1)->once();
        } else {
            $query->shouldReceive('delete')->andReturn(1)->once();
        }

        $handler = $this->createHandler(
            $tenancy,
            $tenant,
            $query
        )->setTenancy($tenancy)->setTenant($tenant);

        $handler->destroy($sessionId);
    }

    public static function databaseSessionDataProvider(): array
    {
        $tenancy = Mockery::mock(Tenancy::class);
        $tenant  = Mockery::mock(Tenant::class)->makePartial();

        return [
            [null, null],
            [$tenancy, $tenant],
        ];
    }
}
