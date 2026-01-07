<?php
declare(strict_types=1);

namespace Sprout\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Database\Eloquent\Scopes\BelongsToManyTenantsScope;
use Sprout\Exceptions\TenantMismatchException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Managers\TenancyManager;
use Sprout\TenancyOptions;
use Workbench\App\Models\TenantChildren;
use Workbench\App\Models\TenantChildrenOptional;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

class BelongsToManyTenantsChildModelTest extends FeatureTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    #[Test]
    public function automaticallyAssociatesChildModelWithCurrentTenant(): void
    {
        $tenant = TenantModel::factory()->createOne();

        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $child = TenantChildren::create();

        $this->assertTrue($child->relationLoaded('tenants'));
        $this->assertTrue($child->tenants->contains($tenant));
    }

    #[Test]
    public function automaticallyAddsClauseToTenantQuery(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();
        $tenant3 = TenantModel::factory()->createOne();

        $tenant1children = TenantChildren::factory()->count(10)->afterCreating(function (TenantChildren $child) use ($tenant1) {
            $child->tenants()->attach($tenant1);
        })->create();

        $tenant2children = TenantChildren::factory()->count(7)->afterCreating(function (TenantChildren $child) use ($tenant2) {
            $child->tenants()->attach($tenant2);
        })->create();

        $tenant3children = TenantChildren::factory()->count(3)->afterCreating(function (TenantChildren $child) use ($tenant3) {
            $child->tenants()->attach($tenant3);
        })->create();

        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant1);

        $children = TenantChildren::all();

        $this->assertCount(10, $children);
        $this->assertTrue($children->diff($tenant1children)->isEmpty());

        $tenancy->setTenant($tenant2);

        $children = TenantChildren::all();

        $this->assertCount(7, $children);
        $this->assertTrue($children->diff($tenant2children)->isEmpty());

        $tenancy->setTenant($tenant3);

        $children = TenantChildren::all();

        $this->assertCount(3, $children);
        $this->assertTrue($children->diff($tenant3children)->isEmpty());
    }

    #[Test]
    public function doesNotLetYouQueryAnotherTenantsChildModels(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();

        $tenant1Child = TenantChildren::factory()->afterCreating(function (TenantChildren $child) use ($tenant1) {
            $child->tenants()->attach($tenant1);
        })->createOne();

        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant2);

        $child = TenantChildren::query()->whereKey($tenant1Child->getKey())->first();

        $this->assertNull($child);
    }

    #[Test]
    public function letsYouBypassTenantRestrictions(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();

        $tenant1Child = TenantChildren::factory()->afterCreating(function (TenantChildren $child) use ($tenant1) {
            $child->tenants()->attach($tenant1);
        })->createOne();

        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant2);

        $child1 = TenantChildren::query()->whereKey($tenant1Child->getKey())->first();

        $this->assertNull($child1);

        $child2 = TenantChildren::withoutTenantRestrictions(static function () use ($tenant1Child) {
            return TenantChildren::query()->whereKey($tenant1Child->getKey())->first();
        });

        $this->assertNotNull($child2);
        $this->assertTrue($child2->is($tenant1Child));
    }

    #[Test]
    public function errorsOutWhenTheresNoTenantButThereIsATenancyWhenQuerying(): void
    {
        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $this->expectException(TenantMissingException::class);
        $this->expectExceptionMessage('There is no current tenant for tenancy [tenants]');

        TenantChildren::all();
    }

    #[Test]
    public function doesNotErrorOutWhenTheresNoTenantButThereIsATenancyWhenQueryingIfTenantIsOptional(): void
    {
        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        $tenant = TenantModel::factory()->createOne();

        $tenantOwned = TenantChildrenOptional::factory()->afterCreating(function (TenantChildrenOptional $child) use ($tenant) {
            $child->tenants()->attach($tenant);
        })->count(3)->create();

        $notTenantOwned = TenantChildrenOptional::factory()->count(11)->create();

        $tenant2 = TenantModel::factory()->createOne();

        $wrongTenantOwned = TenantChildrenOptional::factory()->afterCreating(function (TenantChildrenOptional $child) use ($tenant2) {
            $child->tenants()->attach($tenant2);
        })->count(5)->create();

        sprout()->setCurrentTenancy($tenancy);

        $children = TenantChildrenOptional::all();

        $this->assertTrue($children->isNotEmpty());
        $this->assertCount(19, $children);
    }

    #[Test]
    public function errorsOutWhenTheresNoTenantButThereIsATenancyWhenCreating(): void
    {
        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $this->expectException(TenantMissingException::class);
        $this->expectExceptionMessage('There is no current tenant for tenancy [tenants]');

        TenantChildren::create();
    }

    #[Test]
    public function doesNotErrorOutWhenTheresNoTenantButThereIsATenancyWhenCreatingIfTenantIsOptional(): void
    {
        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $model = TenantChildrenOptional::create();

        $this->assertTrue($model->exists);
        $this->assertNull($model->tenant);
    }

    #[Test]
    public function canReturnTenantOwnedAndNonTenantOwnedIfTenantOptional(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $child1 = TenantChildrenOptional::factory()->afterCreating(function (TenantChildrenOptional $child) use ($tenant) {
            $child->tenants()->attach($tenant);
        })->createOne();

        $child2 = TenantChildrenOptional::factory()->createOne();

        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $children = TenantChildrenOptional::all();

        $this->assertCount(2, $children);
        $this->assertTrue($children->contains($child1));
        $this->assertTrue($children->contains($child2));
    }

    #[Test]
    public function errorsOutIfTheresATenantMismatch(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();

        $child = TenantChildren::factory()->afterCreating(function (TenantChildren $child) use ($tenant1) {
            $child->tenants()->attach($tenant1);
        })->createOne();

        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant2);

        $this->expectException(TenantMismatchException::class);
        $this->expectExceptionMessage('Model [' . TenantChildren::class . '] already has a tenant, but it is not the current tenant for the tenancy [tenants]');

        TenantChildren::query()->withoutTenants()->whereKey($child->getKey())->first();
    }

    #[Test]
    public function doesNotErrorOutIfTheresATenantMismatchIfTheTenancyOptionIsNotSet(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();

        $child = TenantChildren::factory()->afterCreating(function (TenantChildren $child) use ($tenant1) {
            $child->tenants()->attach($tenant1);
        })->createOne();

        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        $tenancy->removeOption(TenancyOptions::throwIfNotRelated());

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant2);

        $newChild = TenantChildren::query()->withoutTenants()->whereKey($child->getKey())->first();

        $this->assertTrue($child->is($newChild));
        $this->assertFalse($newChild->tenants->contains($tenant2));
    }

    #[Test]
    public function doesNotInterfereIfOutsideMultitenantedContext(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();

        $child = TenantChildren::factory()->afterCreating(function (TenantChildren $child) use ($tenant1) {
            $child->tenants()->attach($tenant1);
        })->createOne();

        $newChild = TenantChildren::query()->withoutTenants()->whereKey($child->getKey())->first();

        $this->assertTrue($child->is($newChild));
        $this->assertFalse($newChild->tenants->contains($tenant2));
    }

    #[Test]
    public function doesNotHydrateTenantRelationIfTheTenancyOptionIsNotSet(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $child = TenantChildren::factory()->afterCreating(function (TenantChildren $child) use ($tenant) {
            $child->tenants()->attach($tenant);
        })->createOne();

        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $tenancy->removeOption(TenancyOptions::hydrateTenantRelation());

        $newChild = TenantChildren::query()->whereKey($child->getKey())->first();

        $this->assertTrue($child->is($newChild));
        $this->assertFalse($newChild->relationLoaded('tenants'));
    }

    #[Test]
    public function doesNotHydrateTenantRelationIfTheTenancyOptionIsNotSetWhenCreating(): void
    {
        $tenant = TenantModel::factory()->createOne();

        /** @var \Sprout\Support\DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $tenancy->removeOption(TenancyOptions::hydrateTenantRelation());

        $child = TenantChildren::create();

        $this->assertTrue($child->exists);
        $this->assertFalse($child->relationLoaded('tenants'));
    }
}
