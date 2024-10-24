<?php
declare(strict_types=1);

namespace Sprout\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

/**
 * Tenant Found Event
 *
 * This is an event used to notify the application and provide reactivity when
 * a tenant is found.
 * It is an abstract base class that exists so that developers can listen to
 * all events where tenants are found, regardless of the method used.
 *
 * @see \Sprout\Events\TenantIdentified
 * @see \Sprout\Events\TenantLoaded
 *
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
     * The tenancy whose tenant was found
     *
     * @var \Sprout\Contracts\Tenancy
     *
     * @phpstan-var \Sprout\Contracts\Tenancy<TenantClass>
     */
    public Tenancy $tenancy;

    /**
     * The tenant that was found
     *
     * @var \Sprout\Contracts\Tenant
     *
     * @phpstan-var TenantClass
     */
    public Tenant $tenant;

    /**
     * Create a new instance
     *
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
