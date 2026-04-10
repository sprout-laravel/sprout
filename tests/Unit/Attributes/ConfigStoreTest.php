<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Attributes;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\ConfigStore;
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
}
