<?php
declare(strict_types=1);

namespace Sprout\Overrides\Session;

use Illuminate\Database\Query\Builder;
use Illuminate\Session\DatabaseSessionHandler;
use Sprout\Exceptions\TenancyMissing;
use Sprout\Exceptions\TenantMissing;
use function Sprout\sprout;

/**
 * Tenant Aware Database Session Handler
 *
 * This is a database session driver that wraps the default
 * {@see \Illuminate\Session\DatabaseSessionHandler} and adds a where clause
 * to the query to ensure sessions are tenanted.
 *
 * @package Overrides
 */
class TenantAwareDatabaseSessionHandler extends DatabaseSessionHandler
{
    /**
     * Get a fresh query builder instance for the table.
     *
     * @return \Illuminate\Database\Query\Builder
     *
     * @throws \Sprout\Exceptions\TenantMissing
     * @throws \Sprout\Exceptions\TenancyMissing
     */
    protected function getQuery(): Builder
    {
        $tenancy = sprout()->getCurrentTenancy();

        if ($tenancy === null) {
            throw TenancyMissing::make();
        }

        if ($tenancy->check() === false) {
            throw TenantMissing::make($tenancy->getName());
        }

        return parent::getQuery()
                     ->where('tenancy', '=', $tenancy->getName())
                     ->where('tenant_id', '=', $tenancy->key());
    }

    /**
     * Perform an insert operation on the session ID.
     *
     * @param string               $sessionId
     * @param array<string, mixed> $payload
     *
     * @return bool|null
     *
     * @throws \Sprout\Exceptions\TenancyMissing
     * @throws \Sprout\Exceptions\TenantMissing
     */
    protected function performInsert($sessionId, $payload): ?bool
    {
        $tenancy = sprout()->getCurrentTenancy();

        if ($tenancy === null) {
            throw TenancyMissing::make();
        }

        if ($tenancy->check() === false) {
            throw TenantMissing::make($tenancy->getName());
        }

        $payload['tenancy']   = $tenancy->getName();
        $payload['tenant_id'] = $tenancy->key();

        return parent::performInsert($sessionId, $payload);
    }
}
