<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Illuminate\Support\Facades\Context;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Sprout;

final class SetCurrentTenantContext
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
        $contextKey = 'sprout.tenants.' . $event->tenancy->getName();

        if ($event->current === null && Context::has($contextKey)) {
            Context::forget($contextKey);
        } else if ($event->current !== null && ! Context::has($contextKey)) {
            Context::add($contextKey, $event->current->getTenantKey());
        }
    }
}
