<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Sprout\Events\CurrentTenantChanged;

/**
 * Perform Identity Resolver Setup
 *
 * This class is an event listener for {@see \Sprout\Events\CurrentTenantChanged}
 * that handles the setup action hook for the current resolver.
 *
 * @package Core
 */
final class PerformIdentityResolverSetup
{
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
        $event->tenancy->resolver()?->setup($event->tenancy, $event->current);
    }
}
