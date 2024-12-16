<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

/**
 * Tenant Mismatch Exception
 *
 * This exception is used when a child model belongs to a tenant other than
 * the current one.
 *
 * @package Core
 */
final class TenantMismatchException extends SproutException
{
    /**
     * Create the exception
     *
     * @param string      $model
     * @param string|null $tenancy
     *
     * @return self
     */
    public static function make(string $model, ?string $tenancy): self
    {
        return new self(
            'Model [' . $model . '] already has a tenant, but it is not the current tenant for the tenancy'
            . ($tenancy ? '  [' . $tenancy . ']' : '')
        );
    }
}
