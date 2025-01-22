<?php
declare(strict_types=1);

namespace Sprout\Overrides\Session;

use Illuminate\Database\Query\Builder;
use Illuminate\Session\DatabaseSessionHandler;
use Sprout\Concerns\AwareOfTenant;
use Sprout\Contracts\TenantAware;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use function Sprout\sprout;

/**
 * Sprout Database Session Handler
 *
 * This is a database session driver that wraps the default
 * {@see \Illuminate\Session\DatabaseSessionHandler} and adds a where clause
 * to the query to ensure sessions are tenanted.
 *
 * @package Overrides
 */
class SproutDatabaseSessionHandler extends DatabaseSessionHandler implements TenantAware
{
    use AwareOfTenant;

    /**
     * Get a fresh query builder instance for the table.
     *
     * @return \Illuminate\Database\Query\Builder
     *
     * @throws \Sprout\Exceptions\TenantMissingException
     * @throws \Sprout\Exceptions\TenancyMissingException
     */
    protected function getQuery(): Builder
    {
        if (! $this->hasTenant()) {
            return parent::getQuery();
        }

        $tenancy = $this->getTenancy();
        $tenant = $this->getTenant();

        /**
         * @var \Sprout\Contracts\Tenancy<*> $tenancy
         * @var \Sprout\Contracts\Tenant $tenant
         */

        return parent::getQuery()
                     ->where('tenancy', '=', $tenancy->getName())
                     ->where('tenant_id', '=', $tenant->getTenantKey());
    }

    /**
     * Perform an insert operation on the session ID.
     *
     * @param string               $sessionId
     * @param array<string, mixed> $payload
     *
     * @return bool|null
     */
    protected function performInsert($sessionId, $payload): ?bool
    {
        if (! $this->hasTenant()) {
            return parent::performInsert($sessionId, $payload);
        }

        $tenancy = $this->getTenancy();
        $tenant = $this->getTenant();

        /**
         * @var \Sprout\Contracts\Tenancy<*> $tenancy
         * @var \Sprout\Contracts\Tenant $tenant
         */

        $payload['tenancy']   = $tenancy->getName();
        $payload['tenant_id'] = $tenant->getTenantKey();

        return parent::performInsert($sessionId, $payload);
    }
}
