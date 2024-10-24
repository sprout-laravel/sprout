<?php
declare(strict_types=1);

namespace Sprout\Events;

use Illuminate\Foundation\Bus\Dispatchable;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

/**
 * Current Tenant Changed Event
 *
 * This is an event used to notify the application and provide reactivity when
 * the current tenant for a tenancy changes.
 *
 * This event is dispatched when the value for the current tenant changes to
 * a different value, including situations where one or the other is <code>null</code>.
 *
 * Sprout makes heavy use of this event internally to bootstrap tenants tenancies.
 *
 * @template TenantClass of Tenant
 *
 * @method static void dispatch(Tenancy $tenancy, Tenant|null $previous, Tenant|null $current)
 * @method static void dispatchIf(bool $condition, Tenancy $tenancy, Tenant|null $previous, Tenant|null $current)
 * @method static void dispatchUnless(bool $condition, Tenancy $tenancy, Tenant|null $previous, Tenant|null $current)
 *
 * @package Core
 */
final readonly class CurrentTenantChanged
{
    use Dispatchable;

    /**
     * The tenancy whose current tenant changed
     *
     * @var \Sprout\Contracts\Tenancy<TenantClass>
     */
    public Tenancy $tenancy;

    /**
     * The current tenant
     *
     * @var \Sprout\Contracts\Tenant|null
     *
     * @phpstan-var TenantClass|null
     */
    public ?Tenant $current;

    /**
     * The previous tenant
     *
     * @var \Sprout\Contracts\Tenant|null
     *
     * @phpstan-var TenantClass|null
     */
    public ?Tenant $previous;

    /**
     * Create a new instance
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant|null          $previous
     * @param \Sprout\Contracts\Tenant|null          $current
     *
     * @phpstan-param TenantClass|null               $previous
     * @phpstan-param TenantClass|null               $current
     */
    public function __construct(
        Tenancy $tenancy,
        ?Tenant $previous = null,
        ?Tenant $current = null
    )
    {
        $this->tenancy  = $tenancy;
        $this->previous = $previous;
        $this->current  = $current;
    }
}
