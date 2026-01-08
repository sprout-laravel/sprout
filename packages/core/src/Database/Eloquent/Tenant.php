<?php
declare(strict_types=1);

namespace Sprout\Core\Database\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Sprout\Core\Contracts\Tenant as TenantContract;
use Sprout\Core\Database\Eloquent\Concerns\IsTenant;

/**
 * Abstract Tenant Class
 *
 * This class exists for simplicity’s sake, allowing users to extend
 * rather than implement.
 *
 * @package Database\Eloquent
 */
abstract class Tenant extends Model implements TenantContract
{
    use IsTenant;
}
