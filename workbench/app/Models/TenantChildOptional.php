<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Sprout\Core\Attributes\TenantRelation;
use Sprout\Core\Database\Eloquent\Concerns\BelongsToTenant;
use Sprout\Core\Database\Eloquent\Contracts\OptionalTenant;
use Workbench\Database\Factories\TenantChildOptionalFactory;

class TenantChildOptional extends Model implements OptionalTenant
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Workbench\Database\Factories\TenantChildFactory> */
    use HasFactory, BelongsToTenant;

    protected $table = 'tenant_child1';

    protected static string $factory = TenantChildOptionalFactory::class;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Workbench\App\Models\TenantModel, self>
     */
    #[TenantRelation]
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantModel::class, 'tenant_id');
    }
}
