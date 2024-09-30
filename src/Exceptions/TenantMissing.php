<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

final class TenantMissing extends SproutException
{
    public static function make(string $tenancy): self
    {
        return new self(
            'There is no current tenant for tenancy [' . $tenancy . ']'
        );
    }
}
