<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Sprout\Attributes\TenantRelation;
use Sprout\Database\Eloquent\Concerns\BelongsToTenant;
use Workbench\Database\Factories\TenantChildFactory;

class TenantChild extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Workbench\Database\Factories\TenantChildFactory> */
    use HasFactory, BelongsToTenant;

    protected static string $factory = TenantChildFactory::class;

    protected $table = 'tenant_child1';

    /**
     * @return BelongsTo<TenantModel, $this>
     */
    #[TenantRelation]
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantModel::class, 'tenant_id');
    }
}
