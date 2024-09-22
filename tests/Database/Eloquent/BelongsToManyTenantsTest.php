<?php
declare(strict_types=1);

namespace Sprout\Tests\Database\Eloquent;

use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Events\Dispatcher;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Database\Eloquent\Concerns\BelongsToManyTenants;
use Sprout\Database\Eloquent\Concerns\BelongsToTenant;
use Sprout\Database\Eloquent\Observers\BelongsToManyTenantsObserver;
use Sprout\Database\Eloquent\Observers\BelongsToTenantObserver;
use Sprout\Database\Eloquent\Scopes\BelongsToManyTenantsScope;
use Sprout\Database\Eloquent\Scopes\BelongsToTenantScope;
use Workbench\App\Models\TenantChild;
use Workbench\App\Models\TenantChildren;
use Workbench\App\Models\TenantModel;

class BelongsToManyTenantsTest extends TestCase
{
    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    #[Test]
    public function addsGlobalScope(): void
    {
        $model = new TenantChildren();

        $this->assertContains(BelongsToManyTenants::class, class_uses_recursive($model));
        $this->assertArrayHasKey(BelongsToManyTenantsScope::class, $model->getGlobalScopes());
    }

    #[Test]
    public function addsObservers(): void
    {
        $model      = new TenantChildren();
        $dispatcher = TenantChildren::getEventDispatcher();

        $this->assertContains(BelongsToManyTenants::class, class_uses_recursive($model));

        if ($dispatcher instanceof Dispatcher) {
            $this->assertTrue($dispatcher->hasListeners('eloquent.retrieved: ' . TenantChildren::class));
            $this->assertTrue($dispatcher->hasListeners('eloquent.created: ' . TenantChildren::class));

            $listeners = $dispatcher->getRawListeners();

            $this->assertContains(BelongsToManyTenantsObserver::class . '@retrieved', $listeners['eloquent.retrieved: ' . TenantChildren::class]);
            $this->assertContains(BelongsToManyTenantsObserver::class . '@created', $listeners['eloquent.created: ' . TenantChildren::class]);
        } else {
            $this->markTestIncomplete('Cannot complete the test because a custom dispatcher is in place');
        }
    }
}
