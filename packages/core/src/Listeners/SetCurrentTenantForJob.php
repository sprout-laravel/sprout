<?php
declare(strict_types=1);

namespace Sprout\Core\Listeners;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Sprout\Core\Managers\TenancyManager;
use Sprout\Core\Sprout;

/**
 * Set Current Tenant For Job
 *
 * This class is an event listener for {@see \Illuminate\Queue\Events\JobProcessing}
 * that ensures there are current tenants when processing jobs, utilising
 * Laravels context service.
 *
 * @package Overrides
 */
final class SetCurrentTenantForJob
{
    /**
     * @var \Sprout\Core\Sprout
     */
    private Sprout $sprout;

    /**
     * @var \Sprout\Core\Managers\TenancyManager
     */
    private TenancyManager $tenancies;

    public function __construct(Sprout $sprout, TenancyManager $tenancies)
    {
        $this->sprout    = $sprout;
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
            /** @var \Sprout\Core\Contracts\Tenancy<\Sprout\Core\Contracts\Tenant> $tenancy */
            $tenancy = $this->tenancies->get($tenancyName);

            // It's always the key, so we load instead of identifying
            $tenancy->load($key);

            $this->sprout->setCurrentTenancy($tenancy);
        }
    }
}
