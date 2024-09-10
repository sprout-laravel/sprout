<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sprout\Contracts\Tenant;
use Sprout\Database\Eloquent\Concerns\IsTenant;

class TenantModel extends Model implements Tenant
{
    use IsTenant, HasFactory;

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
