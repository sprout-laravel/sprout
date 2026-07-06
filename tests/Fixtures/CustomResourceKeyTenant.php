<?php
declare(strict_types=1);

namespace Sprout\Tests\Fixtures;

use Workbench\App\Models\TenantModel;

/**
 * A tenant that provides its own resource key generator, used to exercise the
 * generateNewResourceKey() branch of the HasTenantResources concern.
 */
class CustomResourceKeyTenant extends TenantModel
{
    protected $table = 'tenants';

    public function generateNewResourceKey(): mixed
    {
        return 'custom-resource-key';
    }
}
