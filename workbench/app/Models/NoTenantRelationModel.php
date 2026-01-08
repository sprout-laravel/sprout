<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Sprout\Core\Database\Eloquent\Concerns\BelongsToTenant;

class NoTenantRelationModel extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Workbench\Database\Factories\TenantChildFactory> */
    use BelongsToTenant;

    protected $table = 'tenant_child1';
}
