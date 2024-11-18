<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Illuminate\Support\Facades\Context;
use Sprout\Events\CurrentTenantChanged;

/**
 * Set Current Tenant Context
 *
 * This class is an event listener for {@see \Sprout\Events\CurrentTenantChanged}
 * that handles the setting of the current tenants key, within Laravels
 * context service.
 *
 * @package Core
 */
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
        $contextKey = 'sprout.tenants';
        $context    = [];

        if (Context::has($contextKey)) {
            /** @var array<string, int|string> $context */
            $context = Context::get($contextKey, []);
        }

        if ($event->current === null) {
            unset($context[$event->tenancy->getName()]);
        } else {
            $context[$event->tenancy->getName()] = $event->current->getTenantKey();
        }

        Context::add($contextKey, $context);
    }
}
