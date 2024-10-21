<?php
declare(strict_types=1);

namespace Sprout\Overrides\Session;

use Illuminate\Database\Query\Builder;
use Illuminate\Session\DatabaseSessionHandler as OriginalDatabaseSessionHandler;
use RuntimeException;
use Sprout\Exceptions\TenantMissing;
use function Sprout\sprout;

class DatabaseSessionHandler extends OriginalDatabaseSessionHandler
{
    protected function getQuery(): Builder
    {
        $tenancy = sprout()->getCurrentTenancy();

        if ($tenancy === null) {
            throw new RuntimeException('No current tenancy');
        }

        if ($tenancy->check() === false) {
            throw TenantMissing::make($tenancy->getName());
        }

        return parent::getQuery()
                     ->where('tenancy', '=', $tenancy->getName())
                     ->where('tenant_id', '=', $tenancy->key());
    }
}
