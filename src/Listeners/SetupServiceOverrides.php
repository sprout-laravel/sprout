<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Sprout\Events\CurrentTenantChanged;
use Sprout\Sprout;

/**
 * Setup Service Overrides
 *
 * This class is an event listener for {@see \Sprout\Events\CurrentTenantChanged}
 * that sets up any service overrides using their setup action hook.
 *
 * @package Override
 */
final class SetupServiceOverrides
{
    /**
     * @var \Sprout\Sprout
     */
    private Sprout $sprout;

    /**
     * Create a new instance
     *
     * @param \Sprout\Sprout $sprout
     */
    public function __construct(Sprout $sprout)
    {
        $this->sprout = $sprout;
    }

    /**
     * Handle the event
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Events\CurrentTenantChanged<TenantClass> $event
     *
     * @return void
     */
    public function handle(CurrentTenantChanged $event): void
    {
        // If there's no current tenant, we aren't interested
        if ($event->current === null) {
            return;
        }

        foreach ($this->sprout->getOverrides() as $override) {
            $override->setup($event->tenancy, $event->current);
        }
    }
}
