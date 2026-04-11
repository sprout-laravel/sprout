<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Illuminate\Foundation\Application;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantAware;
use Sprout\Events\CurrentTenantChanged;

/**
 * Refresh Tenant Aware Dependencies
 *
 * This class is an event listener for {@see CurrentTenantChanged}
 * that handles the refreshing of the current tenant on classes resolved through
 * the container that implement {@see TenantAware}.
 */
final class RefreshTenantAwareDependencies
{
    /**
     * @var Application
     */
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param CurrentTenantChanged<TenantClass> $event
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
