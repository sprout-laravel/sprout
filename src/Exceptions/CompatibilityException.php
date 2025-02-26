<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

/**
 * Compatibility Exception
 *
 * This exception is used when a component of Sprout is not compatible with
 * another.
 *
 * @package Core
 *
 * @codeCoverageIgnore
 */
final class CompatibilityException extends SproutException
{
    /**
     * Create the exception
     *
     * @param string $firstType
     * @param string $firstName
     * @param string $secondType
     * @param string $secondName
     *
     * @return self
     */
    public static function make(string $firstType, string $firstName, string $secondType, string $secondName): self
    {
        return new self('Cannot use ' . $firstType . ' [' . $firstName . '] with ' . $secondType . ' [' . $secondName . ']');
    }

    /**
     * Create an exception for incompatible optional middleware usage
     *
     * @param string $resolver
     *
     * @return self
     */
    public static function optionalMiddleware(string $resolver): self
    {
        return new self('Cannot use optional tenant middleware with the non-parameter based resolver [' . $resolver . '].');
    }
}
