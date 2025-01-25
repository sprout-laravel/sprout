<?php
declare(strict_types=1);

namespace Sprout\Overrides\Session;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Arr;
use Sprout\Concerns\AwareOfTenant;
use Sprout\Contracts\TenantAware;

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
    protected function getQuery(?bool $write = false): Builder
    {
        if (! $this->hasTenant()) {
            return parent::getQuery();
        }

        $tenancy = $this->getTenancy();
        $tenant  = $this->getTenant();

        /**
         * @var \Sprout\Contracts\Tenancy<*> $tenancy
         * @var \Sprout\Contracts\Tenant $tenant
         */

        $query = parent::getQuery();

        if ($write === false) {
            return $query->where('tenancy', '=', $tenancy->getName())
                         ->where('tenant_id', '=', $tenant->getTenantKey());
        }

        return $query;
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
        if ($this->hasTenant()) {
            $tenancy = $this->getTenancy();
            $tenant  = $this->getTenant();

            /**
             * @var \Sprout\Contracts\Tenancy<*> $tenancy
             * @var \Sprout\Contracts\Tenant $tenant
             */

            $payload['tenancy']   = $tenancy->getName();
            $payload['tenant_id'] = $tenant->getTenantKey();
        }

        try {
            return $this->getQuery(true)->insert(Arr::set($payload, 'id', $sessionId));
        } catch (QueryException) { // @codeCoverageIgnore
            return $this->performUpdate($sessionId, $payload) > 0; // @codeCoverageIgnore
        }
    }

    /**
     * Perform an update operation on the session ID.
     *
     * @param string               $sessionId
     * @param array<string, mixed> $payload
     *
     * @return int
     *
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    protected function performUpdate($sessionId, $payload): int
    {
        return $this->getQuery(true)->where('id', $sessionId)->update($payload);
    }
}
