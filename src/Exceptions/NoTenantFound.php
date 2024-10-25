<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

/**
 * No Tenant Found Exception
 *
 * This exception is used when a tenant was unable to be found for a given
 * tenancy, and was required/necessary.
 *
 * @package Core
 */
final class NoTenantFound extends SproutException
{
    /**
     * Create the exception
     *
     * @param string      $resolver
     * @param string      $tenancy
     * @param string|null $identity
     *
     * @return self
     */
    public static function make(string $resolver, string $tenancy, ?string $identity = null): self
    {
        return new self(
            $identity
                ? 'No valid tenant [' . $tenancy . '] found for \'' . $identity . '\', resolved via [' . $resolver . ']'
                : 'No valid tenant [' . $tenancy . '] found [' . $resolver . ']'
        );
    }
}
