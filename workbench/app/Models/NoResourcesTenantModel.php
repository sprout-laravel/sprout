<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sprout\Contracts\Tenant;
use Sprout\Database\Eloquent\Concerns\IsTenant;
use Workbench\Database\Factories\NoResourcesTenantModelFactory;

class NoResourcesTenantModel extends Model implements Tenant
{
    /**
     * @use \Illuminate\Database\Eloquent\Factories\HasFactory<NoResourcesTenantModelFactory>
     */
    use IsTenant;
    use HasFactory;

    protected static string $factory = NoResourcesTenantModelFactory::class;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'identifier',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
