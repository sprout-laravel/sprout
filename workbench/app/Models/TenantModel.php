<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Database\Eloquent\Concerns\HasTenantResources;
use Sprout\Database\Eloquent\Concerns\IsTenant;
use Workbench\Database\Factories\TenantModelFactory;

class TenantModel extends Model implements Tenant, TenantHasResources
{
    /**
     * @use \Illuminate\Database\Eloquent\Factories\HasFactory<TenantModelFactory>
     */
    use IsTenant, HasFactory, HasTenantResources;

    protected $table = 'tenants';

    protected static string $factory = TenantModelFactory::class;

    protected $fillable = [
        'name',
        'identifier',
        'resource_key',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
