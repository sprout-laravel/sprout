<?php
declare(strict_types=1);

namespace Sprout\Tests\Database\Eloquent;

use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Database\Eloquent\Concerns\BelongsToManyTenants;
use Sprout\Database\Eloquent\Contracts\OptionalTenant;
use Sprout\Database\Eloquent\Observers\BelongsToManyTenantsObserver;
use Sprout\Database\Eloquent\Scopes\BelongsToManyTenantsScope;
use Sprout\Exceptions\TenantMismatch;
use Sprout\Exceptions\TenantMissing;
use Sprout\Managers\TenancyManager;
use Sprout\TenancyOptions;
use Workbench\App\Models\TenantChildOptional;
use Workbench\App\Models\TenantChildren;
use Workbench\App\Models\TenantChildrenOptional;
use Workbench\App\Models\TenantModel;

#[Group('database'), Group('eloquent')]
class BelongsToManyTenantsTest extends TestCase
{
    use WithWorkbench, RefreshDatabase;

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

    #[Test]
    public function automaticallyAssociatesWithTenantWhenCreating(): void
    {
        $tenant = TenantModel::factory()->create();

        app(TenancyManager::class)->get()->setTenant($tenant);

        $child = TenantChildren::factory()->create();

        $this->assertTrue($child->exists);
        $this->assertTrue($child->relationLoaded('tenants'));
        $this->assertNotNull($child->tenants->first(fn (Model $model) => $model->is($tenant)));
    }

    #[Test]
    public function throwsAnExceptionIfTheresNoTenantAndTheTenantIsNotOptionalWhenCreating(): void
    {
        $this->expectException(TenantMissing::class);
        $this->expectExceptionMessage(
            'There is no current tenant for tenancy [tenants]'
        );

        TenantChildren::factory()->create();
    }

    #[Test]
    public function doesNothingIfTheresNoTenantAndTheTenantIsOptionalWithInterfaceWhenCreating(): void
    {
        $child = TenantChildrenOptional::factory()->create();

        $this->assertInstanceOf(OptionalTenant::class, $child);
        $this->assertTrue($child->exists);
        $this->assertFalse($child->relationLoaded('tenant'));
        $this->assertEmpty($child->tenants);
    }

    #[Test]
    public function doesNothingIfTheresNoTenantAndTheTenantIsOptionalWithOverrideWhenCreating(): void
    {
        TenantChildren::ignoreTenantRestrictions();

        $child = TenantChildren::factory()->create();

        TenantChildren::resetTenantRestrictions();

        $this->assertNotInstanceOf(OptionalTenant::class, $child);
        $this->assertTrue($child->exists);
        $this->assertFalse($child->relationLoaded('tenant'));
        $this->assertEmpty($child->tenants);
    }

    #[Test]
    public function doesNothingIfTheTenantIsAlreadySetOnTheModelWhenCreating(): void
    {
        $this->markTestSkipped('This test cannot be performed with a belongs to many relation');
    }

    #[Test]
    public function throwsAnExceptionIfTheTenantIsAlreadySetOnTheModelAndItIsDifferentWhenCreating(): void
    {
        $this->markTestSkipped('This test cannot be performed with a belongs to many relation');
    }

    #[Test]
    public function doesNotThrowAnExceptionForTenantMismatchIfNotSetToWhenCreating(): void
    {
        $this->markTestSkipped('This test cannot be performed with a belongs to many relation');
    }

    #[Test]
    public function automaticallyPopulateTheTenantRelationWhenHydrating(): void
    {
        $tenant = TenantModel::factory()->create();

        app(TenancyManager::class)->get()->setTenant($tenant);

        $child = TenantChildren::query()->find(TenantChildren::factory()->create()->getKey());

        $this->assertTrue($child->exists);
        $this->assertTrue($child->relationLoaded('tenants'));
        $this->assertNotNull($child->tenants->first(fn (Model $model) => $model->is($tenant)));
    }

    #[Test]
    public function throwsAnExceptionIfTheresNoTenantAndTheTenantIsNotOptionalWhenHydrating(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        $tenancy->setTenant($tenant);

        $child = TenantChildren::factory()->create();

        $tenancy->setTenant(null);

        $this->expectException(TenantMissing::class);
        $this->expectExceptionMessage(
            'There is no current tenant for tenancy [tenants]'
        );

        TenantChildren::query()->find($child->getKey());
    }

    #[Test]
    public function doesNothingIfTheresNoTenantAndTheTenantIsOptionalWithInterfaceWhenHydrating(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        $tenancy->setTenant($tenant);

        $child = TenantChildrenOptional::factory()->create();

        $tenancy->setTenant(null);

        $child = TenantChildrenOptional::query()->find($child->getKey());

        $this->assertInstanceOf(OptionalTenant::class, $child);
        $this->assertTrue($child->exists);
        $this->assertFalse($child->relationLoaded('tenants'));
    }

    #[Test]
    public function doesNothingIfTheresNoTenantAndTheTenantIsOptionalWithOverrideWhenHydrating(): void
    {
        TenantChildren:: ignoreTenantRestrictions();

        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        $tenancy->setTenant($tenant);

        $child = TenantChildren::factory()->create();

        $tenancy->setTenant(null);

        $child = TenantChildren::query()->find($child->getKey());

        TenantChildren::resetTenantRestrictions();

        $this->assertNotInstanceOf(OptionalTenant::class, $child);
        $this->assertTrue($child->exists);
        $this->assertFalse($child->relationLoaded('tenants'));
    }

    #[Test]
    public function throwsAnExceptionIfTheTenantIsAlreadySetOnTheModelAndItIsDifferentWhenHydrating(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        $tenancy->setTenant($tenant);
        $tenancy->addOption(TenancyOptions::throwIfNotRelated());

        $child = TenantChildren::factory()->create();

        $tenancy->setTenant(TenantModel::factory()->create());

        $this->expectException(TenantMismatch::class);
        $this->expectExceptionMessage(
            'Model ['
            . TenantChildren::class
            . '] already has a tenant, but it is not the current tenant for the tenancy'
            . '  [tenants]'
        );

        TenantChildren::query()->withoutTenants()->find($child->getKey());
    }

    #[Test]
    public function doesNotThrowAnExceptionForTenantMismatchIfNotSetToWhenHydrating(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        $tenancy->setTenant($tenant);
        $tenancy->removeOption(TenancyOptions::throwIfNotRelated());

        $child = TenantChildren::factory()->create();

        $tenancy->setTenant(TenantModel::factory()->create());

        $child = TenantChildren::query()->withoutTenants()->find($child->getKey());

        $this->assertTrue($child->exists);
        $this->assertTrue($child->relationLoaded('tenants'));
        $this->assertNotNull($child->tenants->first(fn (Model $model) => $model->is($tenant)));
        $this->assertNull($child->tenants->first(fn (Model $model) => $model->is($tenancy->tenant())));
    }

    #[Test]
    public function onlyReturnsModelsForTheCurrentTenant(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        $tenancy->setTenant($tenant);

        $original = TenantChildren::factory()->create();

        $tenancy->setTenant(TenantModel::factory()->create());

        $child = TenantChildren::query()->find($original->getKey());

        $this->assertNull($child);

        $tenancy->setTenant($tenant);

        $child = TenantChildren::query()->find($original->getKey());

        $this->assertNotNull($child);
    }

    #[Test]
    public function ignoresTenantClauseWithBuilderMacro(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        $tenancy->setTenant($tenant);
        $tenancy->removeOption(TenancyOptions::throwIfNotRelated());

        $original = TenantChildren::factory()->create();

        $tenancy->setTenant(TenantModel::factory()->create());

        $child = TenantChildren::query()->withoutTenants()->find($original->getKey());

        $this->assertNotNull($child);

        $tenancy->setTenant($tenant);

        $child = TenantChildren::query()->withoutTenants()->find($original->getKey());

        $this->assertNotNull($child);
    }
}
