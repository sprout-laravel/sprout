<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Attributes;

use Illuminate\Contracts\Container\Container;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\ConfigStore;
use Sprout\Contracts\ConfigStore as ConfigStoreContract;
use Sprout\Managers\ConfigStoreManager;
use Sprout\Tests\Unit\UnitTestCase;

class ConfigStoreTest extends UnitTestCase
{
    protected function defineEnvironment($app)
    {
        tap($app['config'], function ($config) {
            $config->set('multitenancy.defaults.config', 'database');
        });
    }

    #[Test]
    public function resolvesConfigStore(): void
    {
        $manager = $this->app->make(ConfigStoreManager::class);

        $callback1 = static function (#[ConfigStore] \Sprout\Contracts\ConfigStore $store) {
            return $store;
        };

        $callback2 = static function (#[ConfigStore('filesystem')] \Sprout\Contracts\ConfigStore $store) {
            return $store;
        };

        $this->assertSame($manager->get(), $this->app->call($callback1));
        $this->assertSame($manager->get('filesystem'), $this->app->call($callback2));
    }

    #[Test]
    public function resolveDelegatesToTheConfigStoreManager(): void
    {
        $expected = Mockery::mock(ConfigStoreContract::class);

        $manager = Mockery::mock(ConfigStoreManager::class, function (MockInterface $mock) use ($expected) {
            $mock->shouldReceive('get')->with('filesystem')->andReturn($expected)->once();
        });

        $container = Mockery::mock(Container::class, function (MockInterface $mock) use ($manager) {
            $mock->shouldReceive('make')->with(ConfigStoreManager::class)->andReturn($manager)->once();
        });

        $attribute = new ConfigStore('filesystem');

        $this->assertSame($expected, $attribute->resolve($attribute, $container));
    }
}
