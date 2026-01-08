<?php
declare(strict_types=1);

namespace Sprout\Core\Listeners;

use Illuminate\Foundation\Application;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Events\CurrentTenantChanged;

/**
 * Refresh Tenant Aware Dependencies
 *
 * This class is an event listener for {@see \Sprout\Core\Events\CurrentTenantChanged}
 * that handles the refreshing of the current tenant on classes resolved through
 * the container that implement {@see \Sprout\Core\Contracts\TenantAware}.
 *
 * @package Core
 */
final class RefreshTenantAwareDependencies
{
    /**
     * @var \Illuminate\Foundation\Application
     */
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Events\CurrentTenantChanged<TenantClass> $event
     *
     * @return void
     */
    public function handle(CurrentTenantChanged $event): void
    {
        if ($event->current !== null) {
            $this->app->forgetExtenders(Tenant::class);
            $this->app->extend(Tenant::class, fn (?Tenant $tenant) => $tenant);
        }
    }
}
