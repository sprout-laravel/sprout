<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Sprout\Events\CurrentTenantChanged;
use Sprout\Sprout;

final class SetupServiceOverrides
{
    /**
     * @var \Sprout\Sprout
     */
    private Sprout $sprout;

    public function __construct(Sprout $sprout)
    {
        $this->sprout = $sprout;
    }

    /**
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
