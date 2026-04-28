<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenant as TenantContract;
use Sprout\Support\GenericTenant;
use Sprout\Tests\Unit\UnitTestCase;

class GenericTenantTest extends UnitTestCase
{
    #[Test]
    public function implementsTheTenantContract(): void
    {
        $tenant = new GenericTenant();

        $this->assertInstanceOf(TenantContract::class, $tenant);
    }

    #[Test]
    public function constructorStoresProvidedAttributes(): void
    {
        $tenant = new GenericTenant(['identifier' => 'foo', 'id' => 7]);

        $this->assertSame('foo', $tenant->identifier);
        $this->assertSame(7, $tenant->id);
    }

    #[Test]
    public function constructorDefaultsToEmptyAttributes(): void
    {
        $tenant = new GenericTenant();

        $this->assertFalse(isset($tenant->identifier));
        $this->assertFalse(isset($tenant->id));
    }

    #[Test]
    public function magicSetterStoresValues(): void
    {
        $tenant = new GenericTenant();

        $tenant->identifier = 'bar';

        $this->assertSame('bar', $tenant->identifier);
    }

    #[Test]
    public function magicIssetReportsPresenceCorrectly(): void
    {
        $tenant = new GenericTenant(['identifier' => 'foo']);

        $this->assertTrue(isset($tenant->identifier));
        $this->assertFalse(isset($tenant->missing));
    }

    #[Test]
    public function magicUnsetRemovesValues(): void
    {
        $tenant = new GenericTenant(['identifier' => 'foo']);

        unset($tenant->identifier);

        $this->assertFalse(isset($tenant->identifier));
    }

    #[Test]
    public function getTenantIdentifierReturnsTheIdentifierAttribute(): void
    {
        $tenant = new GenericTenant(['identifier' => 'my-tenant', 'id' => 1]);

        $this->assertSame('my-tenant', $tenant->getTenantIdentifier());
    }

    #[Test]
    public function getTenantIdentifierNameReturnsTheDefault(): void
    {
        $tenant = new GenericTenant();

        $this->assertSame('identifier', $tenant->getTenantIdentifierName());
    }

    #[Test]
    public function getTenantKeyReturnsTheIdAttribute(): void
    {
        $tenant = new GenericTenant(['identifier' => 'my-tenant', 'id' => 42]);

        $this->assertSame(42, $tenant->getTenantKey());
    }

    #[Test]
    public function getTenantKeyNameReturnsTheDefault(): void
    {
        $tenant = new GenericTenant();

        $this->assertSame('id', $tenant->getTenantKeyName());
    }
}
