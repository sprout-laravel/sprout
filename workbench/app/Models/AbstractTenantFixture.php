<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Sprout\Database\Eloquent\Tenant;

class AbstractTenantFixture extends Tenant
{
    protected $table = 'tenants';
}
