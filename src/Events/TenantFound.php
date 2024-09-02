<?php
declare(strict_types=1);

namespace Sprout\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

/**
 * @template TenantClass of Tenant
 *
 * @method static void dispatch(Tenant $tenant, Tenancy $tenancy)
 * @method static void dispatchIf(bool $condition, Tenant $tenant, Tenancy $tenancy)
 * @method static void dispatchUnless(bool $condition, Tenant $tenant, Tenancy $tenancy)
 */
abstract readonly class TenantFound
{
    use Dispatchable;

    /**
     * @var \Sprout\Contracts\Tenant
     *
     * @phpstan-var TenantClass
     */
    public Tenant $tenant;

    /**
     * @var \Sprout\Contracts\Tenancy
     *
     * @phpstan-var \Sprout\Contracts\Tenancy<TenantClass>
     */
    public Tenancy $tenancy;

    /**
     * @param \Sprout\Contracts\Tenant                       $tenant
     * @param \Sprout\Contracts\Tenancy                      $tenancy
     *
     * @phpstan-param TenantClass                            $tenant
     * @phpstan-param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     */
    public function __construct(
        Tenant  $tenant,
        Tenancy $tenancy,
    )
    {
        $this->tenant  = $tenant;
        $this->tenancy = $tenancy;
    }
}
