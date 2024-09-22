<?php
declare(strict_types=1);

namespace Sprout\Tests\Database\Eloquent;

use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Sprout\Database\Eloquent\Concerns\IsTenantChild;
use Workbench\App\Models\NoTenantRelationModel;
use Workbench\App\Models\TenantChild;
use Workbench\App\Models\TenantChildren;
use Workbench\App\Models\TenantModel;
use Workbench\App\Models\TooManyTenantRelationModel;

class TenantChildTest extends TestCase
{
    protected $enablesPackageDiscoveries = true;

    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
        });
    }

    #[Test]
    public function canFindTenantRelationUsingAttribute(): void
    {
        $model = new TenantChild();

        $this->assertContains(IsTenantChild::class, class_uses_recursive($model));
        $this->assertSame('tenant', $model->getTenantRelationName());
    }

    #[Test]
    public function canManuallyProvideTenantRelationName(): void
    {
        $model = new TenantChildren();

        $this->assertContains(IsTenantChild::class, class_uses_recursive($model));
        $this->assertSame('tenants', $model->getTenantRelationName());
    }

    #[Test]
    public function throwsAnExceptionIfItCantFindTheTenantRelation(): void
    {
        $model = new NoTenantRelationModel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No tenant relation found in model [' . NoTenantRelationModel::class . ']');

        $model->getTenantRelationName();
    }

    #[Test]
    public function throwsAnExceptionIfThereAreMultipleTenantRelations(): void
    {
        $model = new TooManyTenantRelationModel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Models can only have one tenant relation, [' . TooManyTenantRelationModel::class . '] has 2');

        $model->getTenantRelationName();
    }

    #[Test]
    public function canRetrieveTenantRelationCorrectly(): void
    {
        $model1 = new TenantChild();
        $model2 = new TenantChildren();

        $relation1 = $model1->getTenantRelation();
        $relation2 = $model2->getTenantRelation();

        $this->assertContains(IsTenantChild::class, class_uses_recursive($model1));
        $this->assertContains(IsTenantChild::class, class_uses_recursive($model2));
        $this->assertInstanceOf(BelongsTo::class, $relation1);
        $this->assertInstanceOf(BelongsToMany::class, $relation2);
        $this->assertSame('tenant', $relation1->getRelationName());
        $this->assertSame('tenants', $relation2->getRelationName());
    }

    #[Test]
    public function hasNullTenancyNameByDefault(): void
    {
        $model = new TenantChild();

        $this->assertContains(IsTenantChild::class, class_uses_recursive($model));
        $this->assertNull($model->getTenancyName());
    }

    #[Test]
    public function canManuallyProvideTheTenancyName(): void
    {
        $model = new TenantChildren();

        $this->assertContains(IsTenantChild::class, class_uses_recursive($model));
        $this->assertSame('tenants', $model->getTenancyName());
    }

    #[Test]
    public function canRetrieveTenancyCorrectly(): void
    {
        $model1 = new TenantChild();
        $model2 = new TenantChildren();

        $tenancy1 = $model1->getTenancy();
        $tenancy2 = $model2->getTenancy();

        $this->assertSame('tenants', $tenancy1->getName());
        $this->assertSame('tenants', $tenancy2->getName());
    }
}
