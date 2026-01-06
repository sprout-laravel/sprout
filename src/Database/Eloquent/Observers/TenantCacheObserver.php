<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Observers;

use Sprout\Contracts\Tenant;
use Sprout\Support\TenantCacheInvalidator;

/**
 * Tenant Cache Observer
 *
 * This observer automatically invalidates tenant caches when tenant models
 * are created, updated, or deleted.
 *
 * This observer is opt-in and must be manually registered in your application.
 *
 * @package Database\Eloquent
 */
final class TenantCacheObserver
{
    /**
     * The tenant cache invalidator
     *
     * @var \Sprout\Support\TenantCacheInvalidator
     */
    private TenantCacheInvalidator $invalidator;

    /**
     * Create a new tenant cache observer
     *
     * @param \Sprout\Support\TenantCacheInvalidator $invalidator
     */
    public function __construct(TenantCacheInvalidator $invalidator)
    {
        $this->invalidator = $invalidator;
    }

    /**
     * Handle the tenant "saved" event
     *
     * This is called after a tenant is created or updated.
     *
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    public function saved(Tenant $tenant): void
    {
        $this->invalidate($tenant);
    }

    /**
     * Handle the tenant "deleted" event
     *
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    public function deleted(Tenant $tenant): void
    {
        $this->invalidate($tenant);
    }

    /**
     * Handle the tenant "restored" event
     *
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    public function restored(Tenant $tenant): void
    {
        $this->invalidate($tenant);
    }

    /**
     * Invalidate cache for the given tenant
     *
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    private function invalidate(Tenant $tenant): void
    {
        $this->invalidator->invalidateAcrossProviders($tenant);
    }
}
