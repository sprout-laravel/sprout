<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Illuminate\Support\Facades\Context;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Sprout;

final class HandleTenantContext
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
        $contextKey = $this->sprout->contextKey($event->tenancy);

        if ($event->current === null && Context::has($contextKey)) {
            Context::forget($contextKey);
        } else if ($event->current !== null) {
            Context::add($contextKey, $this->sprout->contextValue($event->current));
        }
    }
}
