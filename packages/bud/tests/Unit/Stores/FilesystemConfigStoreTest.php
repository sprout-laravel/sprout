<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Stores;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Bud\Stores\FilesystemConfigStore;
use Sprout\Bud\Tests\Unit\UnitTestCase;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\MisconfigurationException;

class FilesystemConfigStoreTest extends UnitTestCase
{
    protected function mockTenancy(string $name): Tenancy&MockInterface
    {
        return Mockery::mock(Tenancy::class, function (MockInterface $mock) use ($name) {
            $mock->shouldReceive('getName')->andReturn($name);
        });
    }

    protected function mockTenant(Tenancy $tenancy, string $resourceKey, bool $resources = true, ?Closure $callback = null): Tenant&MockInterface
    {
        $callback = static function (MockInterface $mock) use ($resourceKey, $tenancy, $callback) {
            $mock->shouldReceive('getTenantResourceKey')->andReturn($resourceKey);

            if ($callback !== null) {
                $callback($mock);
            }
        };

        return Mockery::mock(
            ...($resources ? [Tenant::class, TenantHasResources::class, $callback] : [Tenant::class, $callback])
        );
    }

    protected function mockFilesystem(?Closure $callback = null): Filesystem&MockInterface
    {
        return Mockery::mock(Filesystem::class, $callback ?? fn() => null);
    }

    #[Test]
    public function canGetConfigForTenant(): void
    {
        $tenancy         = $this->mockTenancy('my-tenants');
        $resourceKey     = Str::uuid7()->toString();
        $tenant          = $this->mockTenant($tenancy, $resourceKey);
        $encrypter       = new Encrypter(Str::random(32), 'AES-256-CBC');
        $service         = 'database';
        $name            = 'custom-tenant-stuff';
        $config          = [
            'host'     => 'localhost',
            'database' => 'my_database',
        ];
        $encryptedConfig = $encrypter->encrypt($config);
        $filesystem      = $this->mockFilesystem(function (MockInterface $mock) use ($encryptedConfig, $name, $service, $resourceKey) {
            $mock->shouldReceive('get')
                 ->with(
                     'my-tenants'
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 0, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($service)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($name)
                 )
                 ->once()
                 ->andReturn($encryptedConfig);
        });

        $store = new FilesystemConfigStore('filesystem', $encrypter, $filesystem);

        $storedConfig = $store->get($tenancy, $tenant, $service, $name);

        $this->assertSame($config, $storedConfig);
    }

    #[Test]
    public function canGetConfigForTenantAndFailSilentlyIfInvalid(): void
    {
        $tenancy         = $this->mockTenancy('my-tenants');
        $resourceKey     = Str::uuid7()->toString();
        $tenant          = $this->mockTenant($tenancy, $resourceKey);
        $encrypter       = new Encrypter(Str::random(32), 'AES-256-CBC');
        $service         = 'database';
        $name            = 'custom-tenant-stuff';
        $config          = 'not-valid';
        $encryptedConfig = $encrypter->encrypt($config);
        $filesystem      = $this->mockFilesystem(function (MockInterface $mock) use ($encryptedConfig, $name, $service, $resourceKey) {
            $mock->shouldReceive('get')
                 ->with(
                     'my-tenants'
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 0, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($service)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($name)
                 )
                 ->once()
                 ->andReturn($encryptedConfig);
        });

        $store = new FilesystemConfigStore('filesystem', $encrypter, $filesystem);

        $storedConfig = $store->get($tenancy, $tenant, $service, $name);

        $this->assertNull($storedConfig);
    }

    #[Test]
    public function canReturnADefaultValueIfConfigNotFoundForTenant(): void
    {
        $tenancy     = $this->mockTenancy('my-tenants');
        $resourceKey = Str::uuid7()->toString();
        $tenant      = $this->mockTenant($tenancy, $resourceKey);
        $encrypter   = new Encrypter(Str::random(32), 'AES-256-CBC');
        $service     = 'database';
        $name        = 'custom-tenant-stuff';
        $filesystem  = $this->mockFilesystem(function (MockInterface $mock) use ($name, $service, $resourceKey) {
            $mock->shouldReceive('get')
                 ->with(
                     'my-tenants'
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 0, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($service)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($name)
                 )
                 ->once()
                 ->andReturn(null);
        });

        $store = new FilesystemConfigStore('filesystem', $encrypter, $filesystem);

        $storedConfig = $store->get($tenancy, $tenant, $service, $name, [7 => 8]);

        $this->assertSame([7 => 8], $storedConfig);
    }

    #[Test]
    public function canCheckIfConfigExistsForTenant(): void
    {
        $tenancy     = $this->mockTenancy('my-tenants');
        $resourceKey = Str::uuid7()->toString();
        $tenant      = $this->mockTenant($tenancy, $resourceKey);
        $encrypter   = new Encrypter(Str::random(32), 'AES-256-CBC');
        $service     = 'database';
        $name        = 'custom-tenant-stuff';
        $filesystem  = $this->mockFilesystem(function (MockInterface $mock) use ($name, $service, $resourceKey) {
            $mock->shouldReceive('exists')
                 ->with(
                     'my-tenants'
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 0, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($service)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($name)
                 )
                 ->once()
                 ->andReturn(true);

            $mock->shouldReceive('exists')
                 ->with(
                     'my-tenants'
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 0, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($service)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($name . '-not-found')
                 )
                 ->once()
                 ->andReturn(false);
        });

        $store = new FilesystemConfigStore('filesystem', $encrypter, $filesystem);

        $this->assertTrue($store->has($tenancy, $tenant, $service, $name));
        $this->assertFalse($store->has($tenancy, $tenant, $service, $name . '-not-found'));
    }

    #[Test]
    public function canSetConfigForTenant(): void
    {
        $tenancy         = $this->mockTenancy('my-tenants');
        $resourceKey     = Str::uuid7()->toString();
        $tenant          = $this->mockTenant($tenancy, $resourceKey);
        $encrypter       = new Encrypter(Str::random(32), 'AES-256-CBC');
        $service         = 'database';
        $name            = 'custom-tenant-stuff';
        $config          = [
            'host'     => 'localhost',
            'database' => 'my_database',
        ];
        $encryptedConfig = $encrypter->encrypt($config);
        $filesystem      = $this->mockFilesystem(function (MockInterface $mock) use ($encrypter, $encryptedConfig, $name, $service, $resourceKey) {
            $mock->shouldReceive('put')
                 ->with(
                     (
                         'my-tenants'
                         . DIRECTORY_SEPARATOR
                         . Str::substr($resourceKey, 0, 2)
                         . DIRECTORY_SEPARATOR
                         . Str::substr($resourceKey, 2)
                         . DIRECTORY_SEPARATOR
                         . Str::slug($service)
                         . DIRECTORY_SEPARATOR
                         . Str::slug($name)
                     ),
                     Mockery::on(static function (string $value) use ($encryptedConfig, $encrypter) {
                         return $encrypter->decrypt($value, false) === $encrypter->decrypt($encryptedConfig, false);
                     })
                 )
                 ->once()
                 ->andReturn(true);
        });

        $store = new FilesystemConfigStore('filesystem', $encrypter, $filesystem);

        $this->assertTrue($store->set($tenancy, $tenant, $service, $name, $config));
    }

    #[Test]
    public function canAddConfigForTenant(): void
    {
        $tenancy         = $this->mockTenancy('my-tenants');
        $resourceKey     = Str::uuid7()->toString();
        $tenant          = $this->mockTenant($tenancy, $resourceKey);
        $encrypter       = new Encrypter(Str::random(32), 'AES-256-CBC');
        $service         = 'database';
        $name            = 'custom-tenant-stuff';
        $config          = [
            'host'     => 'localhost',
            'database' => 'my_database',
        ];
        $encryptedConfig = $encrypter->encrypt($config);
        $filesystem      = $this->mockFilesystem(function (MockInterface $mock) use ($encrypter, $encryptedConfig, $name, $service, $resourceKey) {
            $mock->shouldReceive('exists')
                 ->with(
                     'my-tenants'
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 0, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($service)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($name)
                 )
                 ->once()
                 ->andReturn(false);

            $mock->shouldReceive('exists')
                 ->with(
                     'my-tenants'
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 0, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::substr($resourceKey, 2)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($service)
                     . DIRECTORY_SEPARATOR
                     . Str::slug($name . '-exists')
                 )
                 ->once()
                 ->andReturn(true);

            $mock->shouldReceive('put')
                 ->with(
                     (
                         'my-tenants'
                         . DIRECTORY_SEPARATOR
                         . Str::substr($resourceKey, 0, 2)
                         . DIRECTORY_SEPARATOR
                         . Str::substr($resourceKey, 2)
                         . DIRECTORY_SEPARATOR
                         . Str::slug($service)
                         . DIRECTORY_SEPARATOR
                         . Str::slug($name)
                     ),
                     Mockery::on(static function (string $value) use ($encryptedConfig, $encrypter) {
                         return $encrypter->decryptString($value) === $encrypter->decryptString($encryptedConfig);
                     })
                 )
                 ->once()
                 ->andReturn(true);
        });

        $store = new FilesystemConfigStore('filesystem', $encrypter, $filesystem);

        $this->assertTrue($store->add($tenancy, $tenant, $service, $name, $config));
        $this->assertFalse($store->add($tenancy, $tenant, $service, $name . '-exists', $config));
    }

    #[Test]
    public function throwsAnExceptionIfTheTenantIsNotConfiguredForResources(): void
    {
        $tenancy         = $this->mockTenancy('my-tenants');
        $resourceKey     = Str::uuid7()->toString();
        $tenant          = $this->mockTenant($tenancy, $resourceKey, false, function (MockInterface $mock) {
            $mock->shouldReceive('getTenantKey')->andReturn(7898);
        });
        $encrypter       = new Encrypter(Str::random(32), 'AES-256-CBC');
        $filesystem      = $this->mockFilesystem();

        $store = new FilesystemConfigStore('filesystem', $encrypter, $filesystem);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The current tenant [7898] is not configured correctly for resources');

        $store->get($tenancy, $tenant, 'made-up', 'does-not-exist');
    }
}
