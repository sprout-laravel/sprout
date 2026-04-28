<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Database\Eloquent;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenant as TenantContract;
use Sprout\Database\Eloquent\Tenant as AbstractTenant;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\AbstractTenantFixture;

class AbstractTenantTest extends UnitTestCase
{
    #[Test]
    public function fixtureExtendsTheAbstractTenantClass(): void
    {
        $fixture = new AbstractTenantFixture();

        $this->assertInstanceOf(AbstractTenant::class, $fixture);
    }

    #[Test]
    public function fixtureIsAnEloquentModel(): void
    {
        $fixture = new AbstractTenantFixture();

        $this->assertInstanceOf(Model::class, $fixture);
    }

    #[Test]
    public function fixtureImplementsTheTenantContract(): void
    {
        $fixture = new AbstractTenantFixture();

        $this->assertInstanceOf(TenantContract::class, $fixture);
    }

    #[Test]
    public function isTenantTraitProvidesDefaultIdentifierAndKeyNames(): void
    {
        $fixture = new AbstractTenantFixture();

        $this->assertSame('identifier', $fixture->getTenantIdentifierName());
        $this->assertSame('id', $fixture->getTenantKeyName());
    }
}
