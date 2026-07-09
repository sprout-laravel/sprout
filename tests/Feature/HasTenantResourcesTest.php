<?php
declare(strict_types=1);

namespace Sprout\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Tests\Fixtures\CustomResourceKeyTenant;

class HasTenantResourcesTest extends FeatureTestCase
{
    use RefreshDatabase;

    #[Test]
    public function usesTheModelsResourceKeyGeneratorWhenDefined(): void
    {
        // The model overrides generateNewResourceKey(), so on create the resource key
        // must come from it rather than a random UUID.
        $tenant             = new CustomResourceKeyTenant();
        $tenant->name       = 'Custom';
        $tenant->identifier = 'custom-tenant';
        $tenant->save();

        $this->assertSame('custom-resource-key', $tenant->getAttribute('resource_key'));
    }
}
