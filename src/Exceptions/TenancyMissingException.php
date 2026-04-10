<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

/**
 * Tenancy Missing Exception
 *
 * This exception is used when there is no current tenancy, but one is
 * expected/required.
 *
 * @package Core
 *
 * @codeCoverageIgnore
 */
final class TenancyMissingException extends SproutException
{
    /**
     * Create the exception
     *
     * @return self
     */
    public static function make(): self
    {
        return new self(
            'There is no current tenancy'
        );
    }
}
