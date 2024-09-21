<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

final class TenantMissing extends SproutException
{
    public static function make(string $model, ?string $tenancy): self
    {
        return new self(
            'Model [' . $model . '] cannot be created without a current tenant for the tenancy'
            . ($tenancy ? '  [' . $tenancy . ']' : '')
        );
    }
}
