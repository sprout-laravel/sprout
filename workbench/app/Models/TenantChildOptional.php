<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Sprout\Attributes\TenantRelation;
use Sprout\Database\Eloquent\Concerns\BelongsToTenant;
use Sprout\Database\Eloquent\Contracts\OptionalTenant;
use Workbench\Database\Factories\TenantChildOptionalFactory;

class TenantChildOptional extends Model implements OptionalTenant
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Workbench\Database\Factories\TenantChildFactory> */
    use HasFactory;
    use BelongsToTenant;

    protected static string $factory = TenantChildOptionalFactory::class;

    protected $table = 'tenant_child1';

    /**
     * @return BelongsTo<TenantModel, self>
     */
    #[TenantRelation]
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantModel::class, 'tenant_id');
    }
}
