<?php
declare(strict_types=1);

namespace Sprout\Listeners;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Sprout\Managers\TenancyManager;
use Sprout\TenancyOptions;

final class SetCurrentTenantForJob
{
    /**
     * @var \Sprout\Managers\TenancyManager
     */
    private TenancyManager $tenancies;

    public function __construct(TenancyManager $tenancies)
    {
        $this->tenancies = $tenancies;
    }

    public function handle(JobProcessing $event): void
    {
        /** @var array<string, string|int> $tenants */
        $tenants = Context::get('sprout.tenants', []);

        /**
         * @var string     $tenancyName
         * @var int|string $key
         */
        foreach ($tenants as $tenancyName => $key) {
            /** @var \Sprout\Contracts\Tenancy<*> $tenancy */
            $tenancy = $this->tenancies->get($tenancyName);

            // We don't want to set a tenant if there's already one, and we don't
            // want to set a tenant on tenancies that don't have tenant-aware jobs
            if (! $tenancy->check() && TenancyOptions::shouldJobsBeTenantAware($tenancy)) {
                // It's always the key, so we load instead of identifying
                $tenancy->load($key);
            }
        }
    }
}
