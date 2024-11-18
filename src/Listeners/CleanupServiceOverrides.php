<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Sprout\Events\CurrentTenantChanged;
use Sprout\Sprout;

/**
 * Clean-up Service Overrides
 *
 * This class is an event listener for {@see \Sprout\Events\CurrentTenantChanged}
 * that cleans up any existing service overrides when the tenancy changes.
 *
 * @package Overrides
 */
final class CleanupServiceOverrides
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
     * Handle event
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Events\CurrentTenantChanged<TenantClass> $event
     *
     * @return void
     */
    public function handle(CurrentTenantChanged $event): void
    {
        // If there's no previous tenant, we aren't interested
        if ($event->previous === null) {
            return;
        }

        $this->sprout->cleanupOverrides($event->tenancy, $event->previous);
    }
}
