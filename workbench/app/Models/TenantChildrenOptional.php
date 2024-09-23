<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Sprout\Database\Eloquent\Concerns\BelongsToManyTenants;
use Sprout\Database\Eloquent\Contracts\OptionalTenant;
use Workbench\Database\Factories\TenantChildrenOptionlFactory;

class TenantChildrenOptional extends Model implements OptionalTenant
{
    /**
     * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Workbench\Database\Factories\TenantChildrenFactory>
     */
    use HasFactory, BelongsToManyTenants;

    protected $table = 'tenant_child2';

    protected static string $factory = TenantChildrenOptionlFactory::class;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Workbench\App\Models\TenantModel>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(TenantModel::class, 'tenant_relations', 'tenant_id', 'tenant_child2_id');
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
