<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit;

use Illuminate\Config\Repository;
use Mockery;
use Mockery\MockInterface;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenancy;
use Sprout\TenancyOptions;
use function Sprout\tenancy;

class TenancyOptionsTest extends UnitTestCase
{
    protected function setupSecondTenancy($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.backup', [
                'driver' => 'database',
                'table'  => 'tenants',
            ]);

            $config->set('multitenancy.tenancies.backup', [
                'provider' => 'backup',
            ]);
        });
    }

    #[Test]
    public function hydrateTenantRelationOption(): void
    {
        $this->assertSame('tenant-relation.hydrate', TenancyOptions::hydrateTenantRelation());
    }

    #[Test]
    public function throwIfNotRelatedOption(): void
    {
        $this->assertSame('tenant-relation.strict', TenancyOptions::throwIfNotRelated());
    }

    #[Test]
    public function allOverridesOption(): void
    {
        $this->assertSame('overrides.all', TenancyOptions::allOverrides());
    }

    #[Test]
    public function overridesOption(): void
    {
        $this->assertSame(['overrides' => ['test']], TenancyOptions::overrides(['test']));
    }

    #[Test, DefineEnvironment('setupSecondTenancy')]
    public function correctlyReportsHydrateTenantRelationOptionPresence(): void
    {
        $tenancy = tenancy('tenants');
        $tenancy->removeOption(TenancyOptions::hydrateTenantRelation());

        $this->assertFalse(TenancyOptions::shouldHydrateTenantRelation($tenancy));

        $tenancy->addOption(TenancyOptions::hydrateTenantRelation());

        $this->assertTrue(TenancyOptions::shouldHydrateTenantRelation($tenancy));

        $tenancy = tenancy('backup');

        $this->assertFalse(TenancyOptions::shouldHydrateTenantRelation($tenancy));
    }

    #[Test, DefineEnvironment('setupSecondTenancy')]
    public function correctlyReportsThrowIfNotRelatedOptionPresence(): void
    {
        $tenancy = tenancy('tenants');
        $tenancy->removeOption(TenancyOptions::throwIfNotRelated());

        $this->assertFalse(TenancyOptions::shouldThrowIfNotRelated($tenancy));

        $tenancy->addOption(TenancyOptions::throwIfNotRelated());

        $this->assertTrue(TenancyOptions::shouldThrowIfNotRelated($tenancy));

        $tenancy = tenancy('backup');

        $this->assertFalse(TenancyOptions::shouldThrowIfNotRelated($tenancy));
    }

    #[Test]
    public function useDefaultStoreReturnsTheCorrectOptionShape(): void
    {
        $this->assertSame(
            ['config:store.default' => 'foo'],
            TenancyOptions::useDefaultStore('foo'),
        );
    }

    #[Test]
    public function alwaysUseStoreReturnsTheCorrectOptionShape(): void
    {
        $this->assertSame(
            ['config:store.fixed' => 'foo'],
            TenancyOptions::alwaysUseStore('foo'),
        );
    }

    #[Test]
    public function hasDefaultStoreReportsThePresenceOfTheOption(): void
    {
        $tenancy = Mockery::mock(Tenancy::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasOption')->with('config:store.default')->andReturn(true)->once();
            $mock->shouldReceive('hasOption')->with('config:store.default')->andReturn(false)->once();
        });

        $this->assertTrue(TenancyOptions::hasDefaultStore($tenancy));
        $this->assertFalse(TenancyOptions::hasDefaultStore($tenancy));
    }

    #[Test]
    public function getDefaultStoreReturnsTheConfiguredStringOrNull(): void
    {
        $tenancy = Mockery::mock(Tenancy::class, function (MockInterface $mock) {
            $mock->shouldReceive('optionConfig')->with('config:store.default')->andReturn('foo')->once();
            $mock->shouldReceive('optionConfig')->with('config:store.default')->andReturnNull()->once();
            $mock->shouldReceive('optionConfig')->with('config:store.default')->andReturn(['not-a-string'])->once();
        });

        $this->assertSame('foo', TenancyOptions::getDefaultStore($tenancy));
        $this->assertNull(TenancyOptions::getDefaultStore($tenancy));
        $this->assertNull(TenancyOptions::getDefaultStore($tenancy));
    }

    #[Test]
    public function isLockedToStoreReportsThePresenceOfTheOption(): void
    {
        $tenancy = Mockery::mock(Tenancy::class, function (MockInterface $mock) {
            $mock->shouldReceive('hasOption')->with('config:store.fixed')->andReturn(true)->once();
            $mock->shouldReceive('hasOption')->with('config:store.fixed')->andReturn(false)->once();
        });

        $this->assertTrue(TenancyOptions::isLockedToStore($tenancy));
        $this->assertFalse(TenancyOptions::isLockedToStore($tenancy));
    }

    #[Test]
    public function getLockedStoreReturnsTheConfiguredStringOrNull(): void
    {
        $tenancy = Mockery::mock(Tenancy::class, function (MockInterface $mock) {
            $mock->shouldReceive('optionConfig')->with('config:store.fixed')->andReturn('foo')->once();
            $mock->shouldReceive('optionConfig')->with('config:store.fixed')->andReturnNull()->once();
            $mock->shouldReceive('optionConfig')->with('config:store.fixed')->andReturn(['not-a-string'])->once();
        });

        $this->assertSame('foo', TenancyOptions::getLockedStore($tenancy));
        $this->assertNull(TenancyOptions::getLockedStore($tenancy));
        $this->assertNull(TenancyOptions::getLockedStore($tenancy));
    }
}
