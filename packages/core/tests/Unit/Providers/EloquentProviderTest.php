<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Providers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Exceptions\MisconfigurationException;
use Sprout\Core\Providers\EloquentTenantProvider;
use Sprout\Core\Tests\Unit\UnitTestCase;
use Workbench\App\Models\NoResourcesTenantModel;
use Workbench\App\Models\TenantModel;
use function Sprout\Core\provider;
use function Sprout\Core\sprout;

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

    #[Test]
    public function retrievesTenantsByTheirResourceKey(): void
    {
        $provider = provider('tenants');

        $tenant = TenantModel::factory()->createOne();

        $found = $provider->retrieveByResourceKey($tenant->getTenantResourceKey());

        $this->assertNotNull($found);
        $this->assertTrue($tenant->is($found));

        $this->assertNull($provider->retrieveByResourceKey(Str::uuid()->toString()));
    }

    #[Test]
    public function throwsAnExceptionWhenTheTenantDoesNotSupportResources(): void
    {
        // Oddly, testbench uses custom environments BEFORE the main define
        // environment method on this class, so it overwrites everything, and we
        // have to do this here...annoying
        config()->set('multitenancy.providers.tenants.model', NoResourcesTenantModel::class);

        $provider = provider('tenants');

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The current tenant [' . NoResourcesTenantModel::class . '] is not configured correctly for resources');

        $provider->retrieveByResourceKey(Str::uuid()->toString());
    }
}
