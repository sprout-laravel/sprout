<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Database\Eloquent\Concerns\IsTenant;
use Workbench\Database\Factories\NoResourcesTenantModelFactory;

class NoResourcesTenantModel extends Model implements Tenant
{
    /**
     * @use \Illuminate\Database\Eloquent\Factories\HasFactory<NoResourcesTenantModelFactory>
     */
    use IsTenant, HasFactory;

    protected $table = 'tenants';

    protected static string $factory = NoResourcesTenantModelFactory::class;

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
