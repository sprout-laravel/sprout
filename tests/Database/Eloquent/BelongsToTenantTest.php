<?php
declare(strict_types=1);

namespace Sprout\Tests\Database\Eloquent;

use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Database\Eloquent\Concerns\BelongsToTenant;
use Sprout\Database\Eloquent\Observers\BelongsToTenantObserver;
use Sprout\Database\Eloquent\Scopes\BelongsToTenantScope;
use Workbench\App\Models\TenantChild;
use Workbench\App\Models\TenantModel;

class BelongsToTenantTest extends TestCase
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
        $model = new TenantChild();

        $this->assertContains(BelongsToTenant::class, class_uses_recursive($model));
        $this->assertArrayHasKey(BelongsToTenantScope::class, $model->getGlobalScopes());
    }

    #[Test]
    public function addsObservers(): void
    {
        $model      = new TenantChild();
        $dispatcher = TenantChild::getEventDispatcher();

        $this->assertContains(BelongsToTenant::class, class_uses_recursive($model));

        if ($dispatcher instanceof Dispatcher) {
            $this->assertTrue($dispatcher->hasListeners('eloquent.retrieved: ' . TenantChild::class));
            $this->assertTrue($dispatcher->hasListeners('eloquent.creating: ' . TenantChild::class));

            $listeners = $dispatcher->getRawListeners();

            $this->assertContains(BelongsToTenantObserver::class . '@retrieved', $listeners['eloquent.retrieved: ' . TenantChild::class]);
            $this->assertContains(BelongsToTenantObserver::class . '@creating', $listeners['eloquent.creating: ' . TenantChild::class]);
        } else {
            $this->markTestIncomplete('Cannot complete the test because a custom dispatcher is in place');
        }
    }
}
