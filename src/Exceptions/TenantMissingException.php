<?php
declare(strict_types=1);

namespace Sprout\Core\Exceptions;

/**
 * Tenant Missing Exception
 *
 * This exception is used when a tenancy is without a current tenant, but one
 * is expected/required.
 *
 * @package Core
 *
 * @codeCoverageIgnore
 */
final class TenantMissingException extends SproutException
{
    /**
     * Create the exception
     *
     * @param string $tenancy
     *
     * @return self
     */
    public static function make(string $tenancy): self
    {
        return new self(
            'There is no current tenant for tenancy [' . $tenancy . ']'
        );
    }
}
