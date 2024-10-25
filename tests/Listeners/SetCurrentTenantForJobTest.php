<?php
declare(strict_types=1);

namespace Sprout\Tests\Listeners;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Managers\TenancyManager;
use Sprout\Overrides\AuthOverride;
use Sprout\Overrides\CacheOverride;
use Sprout\Overrides\CookieOverride;
use Sprout\Overrides\JobOverride;
use Sprout\Overrides\SessionOverride;
use Sprout\Overrides\StorageOverride;
use Sprout\TenancyOptions;
use Workbench\App\Jobs\TestTenantJob;
use Workbench\App\Models\TenantModel;

#[Group('listeners')]
class SetCurrentTenantForJobTest extends TestCase
{
    use WithWorkbench, RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    protected function noJobOverride($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.services', [
                StorageOverride::class,
                CacheOverride::class,
                AuthOverride::class,
                CookieOverride::class,
                SessionOverride::class,
            ]);
        });
    }

    #[Test, DefineEnvironment('noJobOverride')]
    public function doesNotSetCurrentTenantForJobWithoutOption(): void
    {
        /** @var \Sprout\Contracts\Tenancy<*> $tenancy */
        $tenancy = app(TenancyManager::class)->get();

        $this->assertFalse($tenancy->check());

        $tenant = TenantModel::factory()->createOne();

        Context::add('sprout.tenants', [$tenancy->getName() => $tenant->getKey()]);

        $this->assertTrue(Context::has('sprout.tenants'));
        $this->assertSame([$tenancy->getName() => $tenant->getKey()], Context::get('sprout.tenants'));

        TestTenantJob::dispatchSync();

        $this->assertFalse($tenancy->check());
    }

    #[Test]
    public function setsCurrentTenantForJobWithOption(): void
    {
        /** @var \Sprout\Contracts\Tenancy<*> $tenancy */
        $tenancy = app(TenancyManager::class)->get();

        $this->assertFalse($tenancy->check());

        $tenant = TenantModel::factory()->createOne();

        Context::add('sprout.tenants', [$tenancy->getName() => $tenant->getKey()]);

        $this->assertTrue(Context::has('sprout.tenants'));
        $this->assertSame([$tenancy->getName() => $tenant->getKey()], Context::get('sprout.tenants'));

        TestTenantJob::dispatchSync();

        $this->assertTrue($tenancy->check());
        $this->assertSame($tenant->getKey(), $tenancy->key());
        $this->assertTrue($tenant->is($tenancy->tenant()));
    }
}
