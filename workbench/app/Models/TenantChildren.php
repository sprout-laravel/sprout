<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Sprout\Database\Eloquent\Concerns\BelongsToManyTenants;
use Workbench\Database\Factories\TenantChildrenFactory;

class TenantChildren extends Model
{
    /**
     * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Workbench\Database\Factories\TenantChildrenFactory>
     */
    use HasFactory, BelongsToManyTenants;

    protected $table = 'tenant_child2';

    protected static string $factory = TenantChildrenFactory::class;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Workbench\App\Models\TenantModel>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(TenantModel::class, 'tenant_relations', 'tenant_child2_id', 'tenant_id');
    }

    public function getTenantRelationName(): string
    {
        return 'tenants';
    }

    public function getTenancyName(): string
    {
        return 'tenants';
    }
}
