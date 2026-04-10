<?php
declare(strict_types=1);

namespace Sprout\Core\Exceptions;

/**
 * No Tenant Found Exception
 *
 * This exception is used when a tenant was unable to be found for a given
 * tenancy, and was required/necessary.
 *
 * @package Core
 *
 * @codeCoverageIgnore
 */
final class NoTenantFoundException extends SproutException
{
    /**
     * Create the exception
     *
     * @param string $resolver
     * @param string $tenancy
     *
     * @return self
     */
    public static function make(string $resolver, string $tenancy): self
    {
        return new self('No valid tenant [' . $tenancy . '] found [' . $resolver . ']');
    }
}
