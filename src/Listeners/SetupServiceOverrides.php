<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Sprout\Events\CurrentTenantChanged;
use Sprout\Managers\ServiceOverrideManager;

/**
 * Setup Service Overrides
 *
 * This class is an event listener for {@see CurrentTenantChanged}
 * that sets up any service overrides using their setup action hook.
 */
final class SetupServiceOverrides
{
    /**
     * @var ServiceOverrideManager
     */
    private ServiceOverrideManager $overrides;

    /**
     * Create a new instance
     *
     * @param ServiceOverrideManager $overrides
     */
    public function __construct(ServiceOverrideManager $overrides)
    {
        $this->overrides = $overrides;
    }

    /**
     * Handle the event
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param CurrentTenantChanged<TenantClass> $event
     *
     * @return void
     */
    public function handle(CurrentTenantChanged $event): void
    {
        // If there's no current tenant, we aren't interested
        if ($event->current === null) {
            return;
        }

        $this->overrides->setupOverrides($event->tenancy, $event->current);
    }
}
