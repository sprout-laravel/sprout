<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Illuminate\Support\Facades\Context;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Sprout;

final class PerformIdentityResolverSetup
{
    /**
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
