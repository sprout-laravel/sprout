<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Managers;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Bud\Managers\ConfigStoreManager;
use Sprout\Bud\Stores\DatabaseConfigStore;
use Sprout\Bud\Stores\FilesystemConfigStore;
use Sprout\Bud\Tests\Unit\UnitTestCase;
use Sprout\Core\Exceptions\MisconfigurationException;

class ConfigStoreManagerTest extends UnitTestCase
{
    /**
     * Mocks an application instance and allows optional customisation via a callback function.
     *
     * @param Closure|null $callback An optional callback to customise the mocks behaviour.
     *
     * @return \Illuminate\Foundation\Application&\Mockery\MockInterface The mocked application instance.
     */
    protected function mockApplication(?Closure $callback = null): Application&MockInterface
    {
        /** @var \Illuminate\Foundation\Application&\Mockery\MockInterface $application */
        $application = Mockery::mock(Application::class, static function (MockInterface $mock) use ($callback) {
            if ($callback !== null) {
                $callback($mock);
            }
        });

        return $application;
    }

    /**
     * Mocks a configuration repository instance and allows optional customisation via a callback function.
     *
     * @param Closure|null $callback An optional callback to customise the mocks behaviour.
     *
     * @return \Illuminate\Config\Repository&\Mockery\MockInterface The mocked configuration repository instance.
     */
    protected function mockConfig(?Closure $callback = null): Repository&MockInterface
    {
        /** @var \Illuminate\Config\Repository&\Mockery\MockInterface $config */
        $config = Mockery::mock(Repository::class, static function (MockInterface $mock) use ($callback) {
            if ($callback !== null) {
                $callback($mock);
            }
        });

        return $config;
    }

    protected function mockFilesystem(?Closure $callback = null): FilesystemManager&MockInterface
    {
        /** @var \Illuminate\Filesystem\FilesystemManager&\Mockery\MockInterface $filesystem */
        $filesystem = Mockery::mock(FilesystemManager::class, static function (MockInterface $mock) use ($callback) {
            if ($callback !== null) {
                $callback($mock);
            }
        });

        return $filesystem;
    }

    protected function getConfigStoreManager(Application $app): ConfigStoreManager
    {
        return new ConfigStoreManager($app);
    }

    #[Test]
    public function hasTheCorrectName(): void
    {
        $manager = $this->getConfigStoreManager($this->mockApplication());

        $this->assertSame('config', $manager->getFactoryName());
    }

    #[Test]
    public function returnsTheCorrectConfigKey(): void
    {
        $manager = $this->getConfigStoreManager($this->mockApplication());

        $this->assertSame('sprout.bud.stores.test1', $manager->getConfigKey('test1'));
        $this->assertSame('sprout.bud.stores.test2', $manager->getConfigKey('test2'));
        $this->assertSame('sprout.bud.stores.test3', $manager->getConfigKey('test3'));
        $this->assertSame('sprout.bud.stores.test4', $manager->getConfigKey('test4'));
    }

    #[Test]
    public function hasDefaultDrivers(): void
    {
        $manager = $this->getConfigStoreManager($this->mockApplication());

        $this->assertTrue($manager->hasDriver('filesystem'));
        $this->assertTrue($manager->hasDriver('database'));
    }

    #[Test]
    public function canBuildTheFilesystemDriver(): void
    {
        $manager = $this->getConfigStoreManager($this->mockApplication(function (MockInterface $mock) {
            $mock->shouldReceive('make')
                 ->with('config')
                 ->once()
                 ->andReturn($this->mockConfig(function (MockInterface $mock) {
                     $mock->shouldReceive('get')
                          ->with('sprout.bud.stores.filesystem')
                          ->once()
                          ->andReturn([
                              'driver' => 'filesystem',
                              'disk'   => 'my-favourite',
                          ]);
                 }));

            $mock->shouldReceive('make')
                 ->with('filesystem')
                 ->once()
                 ->andReturn($this->mockFilesystem(function (MockInterface $mock) {
                     $mock->shouldReceive('disk')
                          ->with('my-favourite')
                          ->once()
                          ->andReturn(Mockery::mock(Filesystem::class));
                 }));

            $mock->shouldReceive('make')
                 ->with('encrypter')
                 ->once()
                 ->andReturn(Mockery::mock(Encrypter::class));
        }));

        $store = $manager->get('filesystem');

        $this->assertSame('filesystem', $store->getName());
        $this->assertInstanceOf(FilesystemConfigStore::class, $store);
    }

    #[Test]
    public function canBuildTheFilesystemDriverWithAScopedDisk(): void
    {
        $manager = $this->getConfigStoreManager($this->mockApplication(function (MockInterface $mock) {
            $mock->shouldReceive('make')
                 ->with('config')
                 ->once()
                 ->andReturn($this->mockConfig(function (MockInterface $mock) {
                     $mock->shouldReceive('get')
                          ->with('sprout.bud.stores.filesystem')
                          ->once()
                          ->andReturn([
                              'driver'    => 'filesystem',
                              'disk'      => 'my-favourite',
                              'directory' => 'my-directory',
                          ]);
                 }));

            $mock->shouldReceive('make')
                 ->with('filesystem')
                 ->once()
                 ->andReturn($this->mockFilesystem(function (MockInterface $mock) {
                     $mock->shouldReceive('createScopedDriver')
                          ->with([
                              'disk'   => 'my-favourite',
                              'prefix' => 'my-directory',
                          ])
                          ->once()
                          ->andReturn(Mockery::mock(Filesystem::class));
                 }));

            $mock->shouldReceive('make')
                 ->with('encrypter')
                 ->once()
                 ->andReturn(Mockery::mock(Encrypter::class));
        }));

        $store = $manager->get('filesystem');

        $this->assertSame('filesystem', $store->getName());
        $this->assertInstanceOf(FilesystemConfigStore::class, $store);
    }

    #[Test]
    public function canBuildTheFilesystemDriverWithCustomEncrypter(): void
    {
        $key     = Str::random(32);
        $manager = $this->getConfigStoreManager($this->mockApplication(function (MockInterface $mock) use ($key) {
            $mock->shouldReceive('make')
                 ->with('config')
                 ->twice()
                 ->andReturn($this->mockConfig(function (MockInterface $mock) use ($key) {
                     $mock->shouldReceive('get')
                          ->with('sprout.bud.stores.filesystem')
                          ->once()
                          ->andReturn([
                              'driver' => 'filesystem',
                              'disk'   => 'my-favourite',
                              'key'    => 'base64:' . base64_encode($key),
                          ]);

                     $mock->shouldReceive('get')
                          ->with('app.cipher', 'AES-256-CBC')
                          ->once()
                          ->andReturn('AES-256-CBC');
                 }));

            $mock->shouldReceive('make')
                 ->with('filesystem')
                 ->once()
                 ->andReturn($this->mockFilesystem(function (MockInterface $mock) {
                     $mock->shouldReceive('disk')
                          ->with('my-favourite')
                          ->once()
                          ->andReturn(Mockery::mock(Filesystem::class));
                 }));

            $mock->shouldReceive('make')
                 ->with('encrypter')
                 ->never();
        }));

        $store = $manager->get('filesystem');

        $this->assertSame('filesystem', $store->getName());
        $this->assertInstanceOf(FilesystemConfigStore::class, $store);

        $encrypter = $store->getEncrypter();

        $this->assertSame($key, $encrypter->getKey());
    }

    #[Test]
    public function throwsAnExceptionWhenThereIsNoDiskForTheFilesystemDriver(): void
    {
        $manager = $this->getConfigStoreManager($this->mockApplication(function (MockInterface $mock) {
            $mock->shouldReceive('make')
                 ->with('config')
                 ->once()
                 ->andReturn($this->mockConfig(function (MockInterface $mock) {
                     $mock->shouldReceive('get')
                          ->with('sprout.bud.stores.filesystem')
                          ->once()
                          ->andReturn([
                              'driver' => 'filesystem',
                          ]);
                 }));
        }));

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The config store [filesystem] is missing a required value for \'disk\'');

        $manager->get('filesystem');
    }

    #[Test]
    public function canBuildTheDatabaseDriver(): void
    {
        $manager = $this->getConfigStoreManager($this->mockApplication(function (MockInterface $mock) {
            $mock->shouldReceive('make')
                 ->with('config')
                 ->once()
                 ->andReturn($this->mockConfig(function (MockInterface $mock) {
                     $mock->shouldReceive('get')
                          ->with('sprout.bud.stores.database')
                          ->once()
                          ->andReturn([
                              'driver' => 'database',
                              'table'  => 'tenant_config',
                          ]);
                 }));

            $mock->shouldReceive('make')
                 ->with('db')
                 ->once()
                 ->andReturn(Mockery::mock(DatabaseManager::class, static function (MockInterface $mock) {
                     $mock->shouldReceive('connection')
                          ->once()
                          ->andReturn(Mockery::mock(Connection::class));
                 }));

            $mock->shouldReceive('make')
                 ->with('encrypter')
                 ->once()
                 ->andReturn(Mockery::mock(Encrypter::class));
        }));

        $store = $manager->get('database');

        $this->assertSame('database', $store->getName());
        $this->assertInstanceOf(DatabaseConfigStore::class, $store);
        $this->assertSame('tenant_config', $store->getTable());
    }

    #[Test]
    public function canBuildTheDatabaseDriverWithSpecificConnectionName(): void
    {
        $manager = $this->getConfigStoreManager($this->mockApplication(function (MockInterface $mock) {
            $mock->shouldReceive('make')
                 ->with('config')
                 ->once()
                 ->andReturn($this->mockConfig(function (MockInterface $mock) {
                     $mock->shouldReceive('get')
                          ->with('sprout.bud.stores.database')
                          ->once()
                          ->andReturn([
                              'driver'     => 'database',
                              'table'      => 'tenant_config',
                              'connection' => 'my-connection',
                          ]);
                 }));

            $mock->shouldReceive('make')
                 ->with('db')
                 ->once()
                 ->andReturn(Mockery::mock(DatabaseManager::class, static function (MockInterface $mock) {
                     $mock->shouldReceive('connection')
                          ->with('my-connection')
                          ->once()
                          ->andReturn(Mockery::mock(Connection::class));
                 }));

            $mock->shouldReceive('make')
                 ->with('encrypter')
                 ->once()
                 ->andReturn(Mockery::mock(Encrypter::class));
        }));

        $store = $manager->get('database');

        $this->assertSame('database', $store->getName());
        $this->assertInstanceOf(DatabaseConfigStore::class, $store);
        $this->assertSame('tenant_config', $store->getTable());
    }

    #[Test]
    public function canBuildTheDatabaseDriverWithCustomEncrypter(): void
    {
        $key     = Str::random(32);
        $manager = $this->getConfigStoreManager($this->mockApplication(function (MockInterface $mock) use ($key) {
            $mock->shouldReceive('make')
                 ->with('config')
                 ->twice()
                 ->andReturn($this->mockConfig(function (MockInterface $mock) use ($key) {
                     $mock->shouldReceive('get')
                          ->with('sprout.bud.stores.database')
                          ->once()
                          ->andReturn([
                              'driver' => 'database',
                              'table'  => 'tenant_config',
                              'key'    => 'base64:' . base64_encode($key),
                          ]);

                     $mock->shouldReceive('get')
                          ->with('app.cipher', 'AES-256-CBC')
                          ->once()
                          ->andReturn('AES-256-CBC');
                 }));

            $mock->shouldReceive('make')
                 ->with('db')
                 ->once()
                 ->andReturn(Mockery::mock(DatabaseManager::class, static function (MockInterface $mock) {
                     $mock->shouldReceive('connection')
                          ->once()
                          ->andReturn(Mockery::mock(Connection::class));
                 }));

            $mock->shouldReceive('make')
                 ->with('encrypter')
                 ->never();
        }));

        $store = $manager->get('database');

        $this->assertSame('database', $store->getName());
        $this->assertInstanceOf(DatabaseConfigStore::class, $store);
        $this->assertSame('tenant_config', $store->getTable());

        $encrypter = $store->getEncrypter();

        $this->assertSame($key, $encrypter->getKey());
    }

    #[Test]
    public function throwsAnExceptionWhenThereIsNoTableForTheDatabaseDriver(): void
    {
        $manager = $this->getConfigStoreManager($this->mockApplication(function (MockInterface $mock) {
            $mock->shouldReceive('make')
                 ->with('config')
                 ->once()
                 ->andReturn($this->mockConfig(function (MockInterface $mock) {
                     $mock->shouldReceive('get')
                          ->with('sprout.bud.stores.database')
                          ->once()
                          ->andReturn([
                              'driver' => 'database',
                          ]);
                 }));
        }));

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The config store [database] is missing a required value for \'table\'');

        $manager->get('database');
    }
}
