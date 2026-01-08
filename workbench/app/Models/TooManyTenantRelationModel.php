<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Sprout\Core\Attributes\TenantRelation;
use Sprout\Core\Database\Eloquent\Concerns\BelongsToTenant;

class TooManyTenantRelationModel extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Workbench\Database\Factories\TenantChildFactory> */
    use BelongsToTenant;

    protected $table = 'tenant_child1';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Workbench\App\Models\TenantModel, self>
     */
    #[TenantRelation]
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantModel::class, 'tenant_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Workbench\App\Models\TenantModel>
     */
    #[TenantRelation]
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(TenantModel::class, 'tenant_relations', 'tenant_id', 'tenant_child2_id');
    }
}
