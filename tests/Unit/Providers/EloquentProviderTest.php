<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Providers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Providers\EloquentTenantProvider;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\provider;

class EloquentProviderTest extends UnitTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    #[Test]
    public function hasARegisteredName(): void
    {
        $provider = provider('tenants');

        $this->assertInstanceOf(EloquentTenantProvider::class, $provider);
        $this->assertSame('tenants', $provider->getName());
    }

    #[Test]
    public function hasAModelClass(): void
    {
        $provider = provider('tenants');

        $this->assertInstanceOf(EloquentTenantProvider::class, $provider);
        $this->assertSame(TenantModel::class, $provider->getModelClass());
    }

    #[Test]
    public function retrievesTenantsByTheirIdentifier(): void
    {
        $provider = provider('tenants');

        $tenant = TenantModel::factory()->createOne();

        $found = $provider->retrieveByIdentifier($tenant->getTenantIdentifier());

        $this->assertNotNull($found);
        $this->assertTrue($tenant->is($found));

        $this->assertNull($provider->retrieveByIdentifier('fake-identifier'));
    }

    #[Test]
    public function retrievesTenantsByTheirKey(): void
    {
        $provider = provider('tenants');

        $tenant = TenantModel::factory()->createOne();

        $found = $provider->retrieveByKey($tenant->getTenantKey());

        $this->assertNotNull($found);
        $this->assertTrue($tenant->is($found));

        $this->assertNull($provider->retrieveByKey(-999));
    }
}