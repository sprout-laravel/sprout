<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Stores;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Bud\Stores\DatabaseConfigStore;
use Sprout\Bud\Tests\Unit\UnitTestCase;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;

class DatabaseConfigStoreTest extends UnitTestCase
{
    protected function mockTenancy(string $name): Tenancy&MockInterface
    {
        return Mockery::mock(Tenancy::class, function (MockInterface $mock) use ($name) {
            $mock->shouldReceive('getName')->andReturn($name);
        });
    }

    protected function mockTenant(Tenancy $tenancy, int|string $key, ?Closure $callback = null): Tenant&MockInterface
    {
        return Mockery::mock(Tenant::class, static function (MockInterface $mock) use ($key, $tenancy, $callback) {
            $mock->shouldReceive('getTenantKey')->andReturn($key);

            if ($callback !== null) {
                $callback($mock);
            }
        });
    }

    protected function mockConnection(?Closure $callback = null): ConnectionInterface&MockInterface
    {
        return Mockery::mock(ConnectionInterface::class, static function (MockInterface $mock) use ($callback) {
            if ($callback !== null) {
                $callback($mock);
            }
        });
    }

    #[Test]
    public function canGetConfigForTenant(): void
    {
        $tenancy         = $this->mockTenancy('my-tenants');
        $key             = 12434;
        $tenant          = $this->mockTenant($tenancy, $key);
        $encrypter       = new Encrypter(Str::random(32), 'AES-256-CBC');
        $service         = 'database';
        $name            = 'custom-tenant-stuff';
        $config          = [
            'host'     => 'localhost',
            'database' => 'my_database',
        ];
        $encryptedConfig = $encrypter->encrypt($config);
        $connection      = $this->mockConnection(function (MockInterface $mock) use ($encryptedConfig, $name, $service, $key) {
            $mock->shouldReceive('table')
                 ->with('tenant_config')
                 ->once()
                 ->andReturn(
                     Mockery::mock(Builder::class, function (MockInterface $mock) use ($encryptedConfig, $key, $name, $service) {
                         $mock->shouldReceive('where')
                              ->with('tenancy', '=', 'my-tenants')
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('tenant_id', '=', $key)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('service', '=', $service)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('name', '=', $name)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('value')
                              ->with('config')
                              ->once()
                              ->andReturn($encryptedConfig);
                     })
                 );
        });

        $store = new DatabaseConfigStore('database', $encrypter, $connection, 'tenant_config');

        $storedConfig = $store->get($tenancy, $tenant, $service, $name);

        $this->assertSame($config, $storedConfig);
    }

    #[Test]
    public function canGetConfigForTenantAndFailSilentlyIfInvalid(): void
    {
        $tenancy         = $this->mockTenancy('my-tenants');
        $key             = 12434;
        $tenant          = $this->mockTenant($tenancy, $key);
        $encrypter       = new Encrypter(Str::random(32), 'AES-256-CBC');
        $service         = 'database';
        $name            = 'custom-tenant-stuff';
        $config          = 'not-valid';
        $encryptedConfig = $encrypter->encrypt($config);
        $connection      = $this->mockConnection(function (MockInterface $mock) use ($encryptedConfig, $name, $service, $key) {
            $mock->shouldReceive('table')
                 ->with('tenant_config')
                 ->once()
                 ->andReturn(
                     Mockery::mock(Builder::class, function (MockInterface $mock) use ($encryptedConfig, $key, $name, $service) {
                         $mock->shouldReceive('where')
                              ->with('tenancy', '=', 'my-tenants')
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('tenant_id', '=', $key)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('service', '=', $service)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('name', '=', $name)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('value')
                              ->with('config')
                              ->once()
                              ->andReturn($encryptedConfig);
                     })
                 );
        });

        $store = new DatabaseConfigStore('database', $encrypter, $connection, 'tenant_config');

        $storedConfig = $store->get($tenancy, $tenant, $service, $name);

        $this->assertNull($storedConfig);
    }

    #[Test]
    public function canReturnADefaultValueIfConfigNotFoundForTenant(): void
    {
        $tenancy    = $this->mockTenancy('my-tenants');
        $key        = 12434;
        $tenant     = $this->mockTenant($tenancy, $key);
        $encrypter  = new Encrypter(Str::random(32), 'AES-256-CBC');
        $service    = 'database';
        $name       = 'custom-tenant-stuff';
        $connection = $this->mockConnection(function (MockInterface $mock) use ($name, $service, $key) {
            $mock->shouldReceive('table')
                 ->with('tenant_config')
                 ->once()
                 ->andReturn(
                     Mockery::mock(Builder::class, function (MockInterface $mock) use ($key, $name, $service) {
                         $mock->shouldReceive('where')
                              ->with('tenancy', '=', 'my-tenants')
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('tenant_id', '=', $key)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('service', '=', $service)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('name', '=', $name)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('value')
                              ->with('config')
                              ->once()
                              ->andReturn(null);
                     })
                 );
        });

        $store = new DatabaseConfigStore('database', $encrypter, $connection, 'tenant_config');

        $storedConfig = $store->get($tenancy, $tenant, $service, $name, ['yes' => 'no']);

        $this->assertSame(['yes' => 'no'], $storedConfig);
    }

    #[Test]
    public function canCheckIfConfigExistsForTenant(): void
    {
        $tenancy    = $this->mockTenancy('my-tenants');
        $key        = 12434;
        $tenant     = $this->mockTenant($tenancy, $key);
        $encrypter  = new Encrypter(Str::random(32), 'AES-256-CBC');
        $service    = 'database';
        $name       = 'custom-tenant-stuff';
        $connection = $this->mockConnection(function (MockInterface $mock) use ($name, $service, $key) {
            $mock->shouldReceive('table')
                 ->with('tenant_config')
                 ->once()
                 ->andReturn(
                     Mockery::mock(Builder::class, function (MockInterface $mock) use ($key, $name, $service) {
                         $mock->shouldReceive('where')
                              ->with('tenancy', '=', 'my-tenants')
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('tenant_id', '=', $key)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('service', '=', $service)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('name', '=', $name)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('whereNotNull')
                              ->with('config')
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('exists')
                              ->once()
                              ->andReturn(true);
                     })
                 );
        });

        $store = new DatabaseConfigStore('database', $encrypter, $connection, 'tenant_config');

        $this->assertTrue($store->has($tenancy, $tenant, $service, $name));
    }

    #[Test]
    public function canSetConfigForTenant(): void
    {
        $tenancy         = $this->mockTenancy('my-tenants');
        $key             = 12434;
        $tenant          = $this->mockTenant($tenancy, $key);
        $encrypter       = new Encrypter(Str::random(32), 'AES-256-CBC');
        $service         = 'database';
        $name            = 'custom-tenant-stuff';
        $config          = [
            'host'     => 'localhost',
            'database' => 'my_database',
        ];
        $encryptedConfig = $encrypter->encrypt($config);
        $connection      = $this->mockConnection(function (MockInterface $mock) use ($encrypter, $encryptedConfig, $name, $service, $key) {
            $mock->shouldReceive('table')
                 ->with('tenant_config')
                 ->once()
                 ->andReturn(
                     Mockery::mock(Builder::class, static function (MockInterface $mock) use ($encrypter, $encryptedConfig, $key, $name, $service) {
                         $mock->shouldReceive('upsert')
                              ->with(
                                  Mockery::on(static function (array $v) use ($encrypter, $service, $name, $encryptedConfig, $key) {
                                      if (! isset($v['tenancy'], $v['tenant_id'], $v['service'], $v['name'], $v['config'])) {
                                          return false;
                                      }

                                      return $v['tenancy'] === 'my-tenants'
                                             && $v['tenant_id'] === $key
                                             && $v['service'] === $service
                                             && $v['name'] === $name
                                             && $encrypter->decryptString($v['config']) === $encrypter->decryptString($encryptedConfig);
                                  }),
                                  Mockery::on(static function (array $v) use ($service, $name, $key) {
                                      if (! isset($v['tenancy'], $v['tenant_id'], $v['service'], $v['name'])) {
                                          return false;
                                      }

                                      return $v['tenancy'] === 'my-tenants'
                                             && $v['tenant_id'] === $key
                                             && $v['service'] === $service
                                             && $v['name'] === $name;
                                  })
                              )
                              ->once()
                              ->andReturn(1);
                     })
                 );
        });

        $store = new DatabaseConfigStore('database', $encrypter, $connection, 'tenant_config');

        $this->assertTrue($store->set($tenancy, $tenant, $service, $name, $config));
    }

    #[Test]
    public function canAddConfigForTenant(): void
    {
        $tenancy         = $this->mockTenancy('my-tenants');
        $key             = 12434;
        $tenant          = $this->mockTenant($tenancy, $key);
        $encrypter       = new Encrypter(Str::random(32), 'AES-256-CBC');
        $service         = 'database';
        $name            = 'custom-tenant-stuff';
        $config          = [
            'host'     => 'localhost',
            'database' => 'my_database',
        ];
        $encryptedConfig = $encrypter->encrypt($config);
        $connection      = $this->mockConnection(function (MockInterface $mock) use ($encrypter, $encryptedConfig, $name, $service, $key) {
            $mock->shouldReceive('table')
                 ->with('tenant_config')
                 ->times(3)
                 ->andReturn(
                     Mockery::mock(Builder::class, static function (MockInterface $mock) use ($encrypter, $encryptedConfig, $key, $name, $service) {
                         $mock->shouldReceive('where')
                              ->with('tenancy', '=', 'my-tenants')
                              ->twice()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('tenant_id', '=', $key)
                              ->twice()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('service', '=', $service)
                              ->twice()
                              ->andReturnSelf();

                         $mock->shouldReceive('where')
                              ->with('name', '=', $name)
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('whereNotNull')
                              ->with('config')
                              ->twice()
                              ->andReturnSelf();

                         $mock->shouldReceive('exists')
                              ->once()
                              ->andReturn(true);

                         $mock->shouldReceive('where')
                              ->with('name', '=', $name . '-exists')
                              ->once()
                              ->andReturnSelf();

                         $mock->shouldReceive('exists')
                              ->once()
                              ->andReturn(false);

                         $mock->shouldReceive('insert')
                              ->with(
                                  Mockery::on(static function (array $v) use ($encrypter, $service, $name, $encryptedConfig, $key) {
                                      if (! isset($v['tenancy'], $v['tenant_id'], $v['service'], $v['name'], $v['config'])) {
                                          return false;
                                      }

                                      return $v['tenancy'] === 'my-tenants'
                                             && $v['tenant_id'] === $key
                                             && $v['service'] === $service
                                             && $v['name'] === $name
                                             && $encrypter->decryptString($v['config']) === $encrypter->decryptString($encryptedConfig);
                                  })
                              )
                              ->once()
                              ->andReturn(1);
                     })
                 );
        });

        $store = new DatabaseConfigStore('database', $encrypter, $connection, 'tenant_config');

        $this->assertFalse($store->add($tenancy, $tenant, $service, $name . '-exists', $config));
        $this->assertTrue($store->add($tenancy, $tenant, $service, $name, $config));
    }
}
