<?php
declare(strict_types=1);

namespace Sprout\Events;

use Illuminate\Foundation\Bus\Dispatchable;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

/**
 * @template TenantClass of Tenant
 *
 * @method static void dispatch(Tenancy $tenancy, Tenant|null $previous, Tenant|null $current)
 * @method static void dispatchIf(bool $condition, Tenancy $tenancy, Tenant|null $previous, Tenant|null $current)
 * @method static void dispatchUnless(bool $condition, Tenancy $tenancy, Tenant|null $previous, Tenant|null $current)
 */
final readonly class CurrentTenantChanged
{
    use Dispatchable;

    /**
     * @var \Sprout\Contracts\Tenancy<TenantClass>
     */
    public Tenancy $tenancy;

    /**
     * @var \Sprout\Contracts\Tenant|null
     *
     * @phpstan-var TenantClass|null
     */
    public ?Tenant $current;

    /**
     * @var \Sprout\Contracts\Tenant|null
     *
     * @phpstan-var TenantClass|null
     */
    public ?Tenant $previous;

    /**
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
