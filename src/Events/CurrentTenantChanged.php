<?php
declare(strict_types=1);

namespace Sprout\Events;

use Illuminate\Foundation\Bus\Dispatchable;
use Sprout\Contracts\Tenant;

/**
 * @template TenantClass of Tenant
 *
 * @method static void dispatch(Tenant|null $previous, Tenant|null $current)
 * @method static void dispatchIf(bool $condition, Tenant|null $previous, Tenant|null $current)
 * @method static void dispatchUnless(bool $condition, Tenant|null $previous, Tenant|null $current)
 */
final readonly class CurrentTenantChanged
{
    use Dispatchable;

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
     * @param \Sprout\Contracts\Tenant|null $previous
     * @param \Sprout\Contracts\Tenant|null $current
     *
     * @phpstan-param TenantClass|null      $previous
     * @phpstan-param TenantClass|null      $current
     */
    public function __construct(
        ?Tenant $previous = null,
        ?Tenant $current = null
    )
    {
        $this->previous = $previous;
        $this->current  = $current;
    }
}
