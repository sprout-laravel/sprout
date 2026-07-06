<?php
declare(strict_types=1);

namespace Sprout\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Database\Eloquent\Scopes\BelongsToTenantScope;
use Sprout\Exceptions\TenantMismatchException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Exceptions\TenantRelationException;
use Sprout\Managers\TenancyManager;
use Sprout\Support\DefaultTenancy;
use Sprout\TenancyOptions;
use Workbench\App\Models\NoTenantRelationModel;
use Workbench\App\Models\TenantChild;
use Workbench\App\Models\TenantChildOptional;
use Workbench\App\Models\TenantModel;
use Workbench\App\Models\TooManyTenantRelationModel;

use function Sprout\sprout;

class BelongsToTenantChildModelTest extends FeatureTestCase
{
    use RefreshDatabase;

    #[Test]
    public function hydratesTheTenantRelationWhenRetrievingAModel(): void
    {
        $tenant = TenantModel::factory()->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $created = TenantChild::create();

        // A fresh query fires the retrieved observer, which hydrates the tenant relation
        $retrieved = TenantChild::query()->findOrFail($created->getKey());

        $this->assertTrue($retrieved->relationLoaded('tenant'));
        $this->assertTrue($tenant->is($retrieved->tenant));
    }

    #[Test]
    public function optionalTenantScopeAlsoMatchesUnownedRows(): void
    {
        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        $tenant = TenantModel::factory()->createOne();
        $other  = TenantModel::factory()->createOne();

        TenantChildOptional::factory()->afterMaking(function (TenantChildOptional $child) use ($tenant) {
            $child->tenant()->associate($tenant);
        })->count(3)->create();

        TenantChildOptional::factory()->count(4)->create();

        TenantChildOptional::factory()->afterMaking(function (TenantChildOptional $child) use ($other) {
            $child->tenant()->associate($other);
        })->count(5)->create();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        // Optional models return the current tenant's rows (3) plus un-owned rows (4),
        // but never another tenant's rows (5)
        $this->assertCount(7, TenantChildOptional::all());
    }

    #[Test]
    public function automaticallyAssociatesChildModelWithCurrentTenant(): void
    {
        $tenant = TenantModel::factory()->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $child = TenantChild::create();

        $this->assertTrue($child->relationLoaded('tenant'));
        $this->assertTrue($tenant->is($child->tenant));
    }

    #[Test]
    public function automaticallyAddsClauseToTenantQuery(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();
        $tenant3 = TenantModel::factory()->createOne();

        $tenant1children = TenantChild::factory()->count(10)->afterMaking(function (TenantChild $child) use ($tenant1) {
            $child->tenant()->associate($tenant1);
        })->create();

        $tenant2children = TenantChild::factory()->count(7)->afterMaking(function (TenantChild $child) use ($tenant2) {
            $child->tenant()->associate($tenant2);
        })->create();

        $tenant3children = TenantChild::factory()->count(3)->afterMaking(function (TenantChild $child) use ($tenant3) {
            $child->tenant()->associate($tenant3);
        })->create();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant1);

        $children = TenantChild::all();

        $this->assertCount(10, $children);
        $this->assertTrue($children->diff($tenant1children)->isEmpty());

        $tenancy->setTenant($tenant2);

        $children = TenantChild::all();

        $this->assertCount(7, $children);
        $this->assertTrue($children->diff($tenant2children)->isEmpty());

        $tenancy->setTenant($tenant3);

        $children = TenantChild::all();

        $this->assertCount(3, $children);
        $this->assertTrue($children->diff($tenant3children)->isEmpty());
    }

    #[Test]
    public function doesNotLetYouQueryAnotherTenantsChildModels(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();

        $tenant1Child = TenantChild::factory()->afterMaking(function (TenantChild $child) use ($tenant1) {
            $child->tenant()->associate($tenant1);
        })->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant2);

        $child = TenantChild::query()->whereKey($tenant1Child->getKey())->first();

        $this->assertNull($child);
    }

    #[Test]
    public function letsYouBypassTenantRestrictions(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();

        $tenant1Child = TenantChild::factory()->afterMaking(function (TenantChild $child) use ($tenant1) {
            $child->tenant()->associate($tenant1);
        })->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant2);

        $child1 = TenantChild::query()->whereKey($tenant1Child->getKey())->first();

        $this->assertNull($child1);

        $child2 = TenantChild::withoutTenantRestrictions(function () use ($tenant1Child) {
            return TenantChild::query()->whereKey($tenant1Child->getKey())->first();
        });

        $this->assertNotNull($child2);
        $this->assertTrue($child2->is($tenant1Child));
    }

    #[Test]
    public function errorsIfTheresNoTenantRelation(): void
    {
        $tenant = TenantModel::factory()->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $this->expectException(TenantRelationException::class);
        $this->expectExceptionMessage('Cannot find tenant relation for model [' . NoTenantRelationModel::class . ']');

        NoTenantRelationModel::create();
    }

    #[Test]
    public function errorsIfThereAreTooManyTenantRelations(): void
    {
        $tenant = TenantModel::factory()->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $this->expectException(TenantRelationException::class);
        $this->expectExceptionMessage('Expected one tenant relation, found 2 in model [' . TooManyTenantRelationModel::class . ']');

        TooManyTenantRelationModel::create();
    }

    #[Test]
    public function errorsOutWhenTheresNoTenantButThereIsATenancyWhenQuerying(): void
    {
        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $this->expectException(TenantMissingException::class);
        $this->expectExceptionMessage('There is no current tenant for tenancy [tenants]');

        TenantChild::all();
    }

    #[Test]
    public function doesNotErrorOutWhenTheresNoTenantButThereIsATenancyWhenQueryingIfTenantIsOptional(): void
    {
        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        $tenant = TenantModel::factory()->createOne();

        $tenantOwned = TenantChildOptional::factory()->afterMaking(function (TenantChildOptional $child) use ($tenant) {
            $child->tenant()->associate($tenant);
        })->count(3)->create();

        $notTenantOwned = TenantChildOptional::factory()->count(11)->create();

        $tenant2 = TenantModel::factory()->createOne();

        $wrongTenantOwned = TenantChildOptional::factory()->afterMaking(function (TenantChildOptional $child) use ($tenant2) {
            $child->tenant()->associate($tenant2);
        })->count(5)->create();

        sprout()->setCurrentTenancy($tenancy);

        $children = TenantChildOptional::all();

        $this->assertTrue($children->isNotEmpty());
        $this->assertCount(19, $children);
    }

    #[Test]
    public function errorsOutWhenTheresNoTenantButThereIsATenancyWhenCreating(): void
    {
        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $this->expectException(TenantMissingException::class);
        $this->expectExceptionMessage('There is no current tenant for tenancy [tenants]');

        TenantChild::create();
    }

    #[Test]
    public function doesNotErrorOutWhenTheresNoTenantButThereIsATenancyWhenCreatingIfTenantIsOptional(): void
    {
        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $model = TenantChildOptional::create();

        $this->assertTrue($model->exists);
        $this->assertNull($model->tenant);
    }

    #[Test]
    public function canReturnTenantOwnedAndNonTenantOwnedIfTenantOptional(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $child1 = TenantChildOptional::factory()->afterMaking(function (TenantChildOptional $child) use ($tenant) {
            $child->tenant()->associate($tenant);
        })->createOne();

        $child2 = TenantChildOptional::factory()->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $children = TenantChildOptional::all();

        $this->assertCount(2, $children);
        $this->assertTrue($children->contains($child1));
        $this->assertTrue($children->contains($child2));
    }

    #[Test]
    public function errorsOutIfTheresATenantMismatch(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();

        $child = TenantChild::factory()->afterMaking(function (TenantChild $child) use ($tenant1) {
            $child->tenant()->associate($tenant1);
        })->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant2);

        $this->expectException(TenantMismatchException::class);
        $this->expectExceptionMessage('Model [' . TenantChild::class . '] already has a tenant, but it is not the current tenant for the tenancy [tenants]');

        TenantChild::query()->withoutTenants()->whereKey($child->getKey())->first();
    }

    #[Test]
    public function doesNotErrorOutIfTheresATenantMismatchIfTheTenancyOptionIsNotSet(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();

        $child = TenantChild::factory()->afterMaking(function (TenantChild $child) use ($tenant1) {
            $child->tenant()->associate($tenant1);
        })->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        $tenancy->removeOption(TenancyOptions::throwIfNotRelated());

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant2);

        $newChild = TenantChild::query()->withoutTenants()->whereKey($child->getKey())->first();

        $this->assertTrue($child->is($newChild));
        $this->assertFalse($newChild->tenant->is($tenant2));
    }

    #[Test]
    public function doesNotInterfereIfOutsideMultitenantedContext(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();

        $child = TenantChild::factory()->afterMaking(function (TenantChild $child) use ($tenant1) {
            $child->tenant()->associate($tenant1);
        })->createOne();

        $newChild = TenantChild::query()->withoutGlobalScope(BelongsToTenantScope::class)->whereKey($child->getKey())->first();

        $this->assertTrue($child->is($newChild));
        $this->assertFalse($newChild->tenant->is($tenant2));
    }

    #[Test]
    public function doesNotHydrateTenantRelationIfTheTenancyOptionIsNotSet(): void
    {
        $tenant = TenantModel::factory()->createOne();

        $child = TenantChild::factory()->afterMaking(function (TenantChild $child) use ($tenant) {
            $child->tenant()->associate($tenant);
        })->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        $tenancy->removeOption(TenancyOptions::hydrateTenantRelation());

        $newChild = TenantChild::query()->whereKey($child->getKey())->first();

        $this->assertTrue($child->is($newChild));
        $this->assertFalse($newChild->relationLoaded('tenant'));
    }

    #[Test]
    public function doesNotOverwriteAMismatchedTenantOnCreateWhenNotThrowing(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        $tenancy->removeOption(TenancyOptions::throwIfNotRelated());

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant2);

        // The model already points at tenant1 while tenant2 is current. With throwing
        // off the observer bails out of the checks and must NOT associate the current
        // tenant over the top — the foreign key stays tenant1.
        $child = new TenantChild();
        $child->tenant()->associate($tenant1);
        $child->save();

        $this->assertSame($tenant1->getKey(), $child->getAttribute('tenant_id'));
    }

    #[Test]
    public function hydratesTheCurrentTenantWhenRetrievingWithTenantRestrictionsBypassed(): void
    {
        $tenant1 = TenantModel::factory()->createOne();
        $tenant2 = TenantModel::factory()->createOne();

        $child = TenantChild::factory()->afterMaking(function (TenantChild $child) use ($tenant1) {
            $child->tenant()->associate($tenant1);
        })->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant2);

        // The child belongs to tenant1, but with restrictions bypassed the observer
        // skips the mismatch check (returns early as "passed") and still hydrates the
        // relation to the current tenant.
        $retrieved = TenantChild::withoutTenantRestrictions(static function () use ($child) {
            return TenantChild::query()->whereKey($child->getKey())->first();
        });

        $this->assertNotNull($retrieved);
        $this->assertTrue($retrieved->relationLoaded('tenant'));
        $this->assertTrue($tenant2->is($retrieved->tenant));
    }

    #[Test]
    public function doesNotReloadTheRelationOnCreateWhenTheForeignKeyAlreadyMatches(): void
    {
        $tenant = TenantModel::factory()->createOne();

        /** @var DefaultTenancy $tenancy */
        $tenancy = $this->app->make(TenancyManager::class)->get();

        sprout()->setCurrentTenancy($tenancy);

        $tenancy->setTenant($tenant);

        // Set only the foreign key (not the relation) to the current tenant, so the
        // observer sees an already-correct model. It must return early without
        // re-associating, leaving the relation unloaded.
        $child = new TenantChild();
        $child->setAttribute('tenant_id', $tenant->getKey());

        $this->assertFalse($child->relationLoaded('tenant'));

        $child->save();

        $this->assertFalse($child->relationLoaded('tenant'));
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }
}
