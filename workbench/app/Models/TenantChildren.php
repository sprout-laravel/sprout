<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Sprout\Attributes\TenantRelation;
use Sprout\Database\Eloquent\Concerns\IsTenantChild;

class TenantChildren extends Model
{
    /**
     * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Workbench\Database\Factories\TenantChildrenFactory>
     */
    use HasFactory, IsTenantChild;

    protected $table = 'tenant_child2';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Workbench\App\Models\TenantModel>
     */
    #[TenantRelation]
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(TenantModel::class, 'tenant_relations', 'tenant_id', 'tenant_child2_id');
    }
}
