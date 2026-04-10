<?php
declare(strict_types=1);

namespace Sprout\Core\Listeners;

use Sprout\Core\Events\CurrentTenantChanged;

/**
 * Perform Identity Resolver Setup
 *
 * This class is an event listener for {@see \Sprout\Core\Events\CurrentTenantChanged}
 * that handles the setup action hook for the current resolver.
 *
 * @package Core
 */
final class PerformIdentityResolverSetup
{
    /**
     * Handle the event
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Events\CurrentTenantChanged<TenantClass> $event
     *
     * @return void
     */
    public function handle(CurrentTenantChanged $event): void
    {
        $event->tenancy->resolver()?->setup($event->tenancy, $event->current);
    }
}
