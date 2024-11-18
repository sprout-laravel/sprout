<?php
declare(strict_types=1);

namespace Sprout\Tests\Database\Eloquent;

use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Database\Eloquent\Concerns\BelongsToTenant;
use Sprout\Database\Eloquent\Contracts\OptionalTenant;
use Sprout\Database\Eloquent\Observers\BelongsToTenantObserver;
use Sprout\Database\Eloquent\Scopes\BelongsToTenantScope;
use Sprout\Exceptions\TenantMismatch;
use Sprout\Exceptions\TenantMissing;
use Sprout\Managers\TenancyManager;
use Sprout\TenancyOptions;
use Workbench\App\Models\TenantChild;
use Workbench\App\Models\TenantChildOptional;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

#[Group('database'), Group('eloquent')]
class BelongsToTenantTest extends TestCase
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

    #[Test]
    public function automaticallyAssociatesWithTenantWhenCreating(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $child = TenantChild::factory()->create();

        $this->assertTrue($child->exists);
        $this->assertTrue($child->relationLoaded('tenant'));
        $this->assertTrue($child->tenant->is($tenant));
    }

    #[Test]
    public function throwsAnExceptionIfTheresNoTenantAndTheTenantIsNotOptionalWhenCreating(): void
    {
        sprout()->setCurrentTenancy(app(TenancyManager::class)->get());

        $this->expectException(TenantMissing::class);
        $this->expectExceptionMessage(
            'There is no current tenant for tenancy [tenants]'
        );

        TenantChild::factory()->create();
    }

    #[Test]
    public function doesNothingIfTheresNoTenantAndTheTenantIsOptionalWithInterfaceWhenCreating(): void
    {
        $child = TenantChildOptional::factory()->create();

        $this->assertInstanceOf(OptionalTenant::class, $child);
        $this->assertTrue($child->exists);
        $this->assertFalse($child->relationLoaded('tenant'));
        $this->assertNull($child->tenant);
    }

    #[Test]
    public function doesNothingIfTheresNoTenantAndTheTenantIsOptionalWithOverrideWhenCreating(): void
    {
        TenantChild::ignoreTenantRestrictions();
        $child = TenantChild::factory()->create();
        TenantChild::resetTenantRestrictions();

        $this->assertNotInstanceOf(OptionalTenant::class, $child);
        $this->assertTrue($child->exists);
        $this->assertFalse($child->relationLoaded('tenant'));
        $this->assertNull($child->tenant);
    }

    #[Test]
    public function doesNothingIfTheTenantIsAlreadySetOnTheModelWhenCreating(): void
    {
        $tenant = TenantModel::factory()->create();

        app(TenancyManager::class)->get()->setTenant($tenant);

        $child = TenantChild::factory()->afterMaking(function (TenantChild $model) use ($tenant) {
            $model->tenant()->associate($tenant);
        })->create();

        $this->assertTrue($child->exists);
        $this->assertTrue($child->relationLoaded('tenant'));
        $this->assertTrue($child->tenant->is($tenant));
    }

    #[Test]
    public function throwsAnExceptionIfTheTenantIsAlreadySetOnTheModelAndItIsDifferentWhenCreating(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $tenancy->addOption(TenancyOptions::throwIfNotRelated());

        $this->expectException(TenantMismatch::class);
        $this->expectExceptionMessage(
            'Model ['
            . TenantChild::class
            . '] already has a tenant, but it is not the current tenant for the tenancy'
            . '  [tenants]'
        );

        TenantChild::factory()->for(TenantModel::factory(), 'tenant')->create();
    }

    #[Test]
    public function doesNotThrowAnExceptionForTenantMismatchIfNotSetToWhenCreating(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        $tenancy->setTenant($tenant);
        $tenancy->removeOption(TenancyOptions::throwIfNotRelated());

        $child = TenantChild::factory()->for(TenantModel::factory(), 'tenant')->create();

        $this->assertTrue($child->exists);
        $this->assertFalse($child->relationLoaded('tenant'));
        $this->assertFalse($child->tenant->is($tenant));
    }

    #[Test]
    public function automaticallyPopulateTheTenantRelationWhenHydrating(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $child = TenantChild::query()->find(TenantChild::factory()->create()->getKey());

        $this->assertTrue($child->exists);
        $this->assertTrue($child->relationLoaded('tenant'));
        $this->assertNotNull($child->getRelation('tenant'));
        $this->assertTrue($child->getRelation('tenant')->is($tenant));
    }

    #[Test]
    public function doNotHydrateWhenHydrateTenantRelationIsMissing(): void
    {
        /** @var \Sprout\Contracts\Tenancy $tenancy */
        $tenancy = app(TenancyManager::class)->get();
        $tenancy->removeOption(TenancyOptions::hydrateTenantRelation());

        $tenant = TenantModel::factory()->create();

        $tenancy->setTenant($tenant);

        $child = TenantChild::query()->find(TenantChild::factory()->create()->getKey());

        $this->assertTrue($child->exists);
        $this->assertFalse($child->relationLoaded('tenant'));
    }

    #[Test]
    public function throwsAnExceptionIfTheresNoTenantAndTheTenantIsNotOptionalWhenHydrating(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $child = TenantChild::factory()->create();

        $tenancy->setTenant(null);

        $this->expectException(TenantMissing::class);
        $this->expectExceptionMessage(
            'There is no current tenant for tenancy [tenants]'
        );

        TenantChild::query()->find($child->getKey());
    }

    #[Test]
    public function doesNothingIfTheresNoTenantAndTheTenantIsOptionalWithInterfaceWhenHydrating(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        $tenancy->setTenant($tenant);

        $child = TenantChildOptional::factory()->create();

        $tenancy->setTenant(null);

        $child = TenantChildOptional::query()->find($child->getKey());

        $this->assertInstanceOf(OptionalTenant::class, $child);
        $this->assertTrue($child->exists);
        $this->assertFalse($child->relationLoaded('tenant'));
    }

    #[Test]
    public function doesNothingIfTheresNoTenantAndTheTenantIsOptionalWithOverrideWhenHydrating(): void
    {
        TenantChild::ignoreTenantRestrictions();

        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        $tenancy->setTenant($tenant);

        $child = TenantChild::factory()->create();

        $tenancy->setTenant(null);

        $child = TenantChild::query()->find($child->getKey());

        TenantChild::resetTenantRestrictions();

        $this->assertNotInstanceOf(OptionalTenant::class, $child);
        $this->assertTrue($child->exists);
        $this->assertFalse($child->relationLoaded('tenant'));
    }

    #[Test]
    public function throwsAnExceptionIfTheTenantIsAlreadySetOnTheModelAndItIsDifferentWhenHydrating(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $tenancy->addOption(TenancyOptions::throwIfNotRelated());

        $child = TenantChild::factory()->create();

        $tenancy->setTenant(TenantModel::factory()->create());

        $this->expectException(TenantMismatch::class);
        $this->expectExceptionMessage(
            'Model ['
            . TenantChild::class
            . '] already has a tenant, but it is not the current tenant for the tenancy'
            . '  [tenants]'
        );

        TenantChild::query()->withoutTenants()->find($child->getKey());
    }

    #[Test]
    public function doesNotThrowAnExceptionForTenantMismatchIfNotSetToWhenHydrating(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $tenancy->removeOption(TenancyOptions::throwIfNotRelated());

        $child = TenantChild::factory()->create();

        $tenancy->setTenant(TenantModel::factory()->create());

        $child = TenantChild::query()->withoutTenants()->find($child->getKey());

        $this->assertTrue($child->exists);
        $this->assertFalse($child->relationLoaded('tenant'));
        $this->assertTrue($child->tenant->is($tenant));
        $this->assertFalse($child->tenant->is($tenancy->tenant()));
    }

    #[Test]
    public function onlyReturnsModelsForTheCurrentTenant(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $original = TenantChild::factory()->create();

        $tenancy->setTenant(TenantModel::factory()->create());

        $child = TenantChild::query()->find($original->getKey());

        $this->assertNull($child);

        $tenancy->setTenant($tenant);

        $child = TenantChild::query()->find($original->getKey());

        $this->assertNotNull($child);
    }

    #[Test]
    public function ignoresTenantClauseWithBuilderMacro(): void
    {
        $tenant = TenantModel::factory()->create();

        $tenancy = app(TenancyManager::class)->get();

        $tenancy->setTenant($tenant);
        $tenancy->removeOption(TenancyOptions::throwIfNotRelated());

        $original = TenantChild::factory()->create();

        $tenancy->setTenant(TenantModel::factory()->create());

        $child = TenantChild::query()->withoutTenants()->find($original->getKey());

        $this->assertNotNull($child);

        $tenancy->setTenant($tenant);

        $child = TenantChild::query()->withoutTenants()->find($original->getKey());

        $this->assertNotNull($child);
    }
}
