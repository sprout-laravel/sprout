<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

final class TenantMissing extends SproutException
{
    public static function make(string $model, ?string $tenancy): self
    {
        return new self(
            'Model [' . $model . '] requires a tenant, and the tenancy'
            . ($tenancy ? ' [' . $tenancy . '] ' : ' ')
            . 'does not have one'
        );
    }
}
