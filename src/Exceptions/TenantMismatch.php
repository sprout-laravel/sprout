<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

final class TenantMismatch extends SproutException
{
    public static function make(string $model, ?string $tenancy): self
    {
        return new self(
            'Model [' . $model . '] already has a tenant, but it is not the current tenant for the tenancy'
            . ($tenancy ? '  [' . $tenancy . ']' : '')
        );
    }
}
