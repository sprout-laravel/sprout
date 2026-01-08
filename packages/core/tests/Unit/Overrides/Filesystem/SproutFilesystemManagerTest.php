<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Overrides\Filesystem;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Overrides\Filesystem\SproutFilesystemManager;
use Sprout\Core\Tests\Unit\UnitTestCase;

class SproutFilesystemManagerTest extends UnitTestCase
{
    #[Test]
    public function canBySyncedFromOriginal(): void
    {
        $original = Mockery::mock(FilesystemManager::class);

        $app = Mockery::mock(Application::class);

        $sprout = new SproutFilesystemManager($app, $original);

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
                              ->with('filesystems.disks.fake-disk')
                              ->andReturn([
                                  'driver' => 'fake',
                              ])
                              ->once();
                     })
                 )
                 ->once();
        });

        $sprout = new SproutFilesystemManager($app);

        $sprout->extend('fake', function (Application $app, array $config) {
            $this->assertArrayHasKey('name', $config);
            $this->assertSame('fake-disk', $config['name']);

            return Mockery::mock(Filesystem::class);
        });

        $sprout->disk('fake-disk');
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
                              ->with('filesystems.disks.fake-disk')
                              ->andReturn([
                                  'name' => 'hi',
                              ])
                              ->once();
                     })
                 )
                 ->once();
        });

        $sprout = new SproutFilesystemManager($app);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Disk [fake-disk] does not have a configured driver.');

        $sprout->disk('fake-disk');
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
                              ->with('filesystems.disks.fake-disk')
                              ->andReturn(null)
                              ->once();
                     })
                 )
                 ->once();
        });

        $sprout = new SproutFilesystemManager($app);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Disk [fake-disk] does not have a configured driver.');

        $sprout->disk('fake-disk');
    }

    #[Test]
    public function canCreateDisksNormally(): void
    {
        $app = Mockery::mock($this->app, static function (Mockery\MockInterface $mock) {
            $mock->makePartial();
        });

        $sprout = new SproutFilesystemManager($app);

        $this->assertInstanceOf(LocalFilesystemAdapter::class, $sprout->disk('local'));
    }

    #[Test]
    public function throwsAnExceptionForDriversThatDoNotExist(): void
    {
        $app = Mockery::mock($this->app, static function (Mockery\MockInterface $mock) {
            $mock->makePartial();
        });

        $app['config']['filesystems.disks.local.driver'] = 'fake';

        $sprout = new SproutFilesystemManager($app);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [fake] is not supported.');

        $sprout->disk('local');
    }
}
