<?php
declare(strict_types=1);

namespace Sprout\Core\Exceptions;

use Sprout\Core\Support\ResolutionHook;

/**
 * Misconfiguration Exception
 *
 * This exception is used when a component of Sprout does not have the correct
 * configuration, whether that's through omissions or incorrect values.
 * This also includes cases where instances of certain classes/interfaces is
 * expected, but not provided.
 *
 * @package Core
 *
 * @codeCoverageIgnore
 */
final class MisconfigurationException extends SproutException
{
    /**
     * Create an exception for when a config value is missing
     *
     * @param string $value
     * @param string $type
     * @param string $name
     *
     * @return self
     */
    public static function missingConfig(string $value, string $type, string $name): self
    {
        return new self('The ' . $type . ' [' . $name . '] is missing a required value for \'' . $value . '\'');
    }

    /**
     * Create an exception for when a config value is invalid
     *
     * @param string $value
     * @param string $type
     * @param string $name
     * @param scalar $realValue
     *
     * @return self
     */
    public static function invalidConfig(string $value, string $type, string $name, mixed $realValue = null): self
    {
        return new self('The provided value for \'' . $value . '\' ' . ($realValue ? '[' . $realValue . '] ' : '') . 'is not valid for ' . $type . ' [' . $name . ']');
    }

    /**
     * Create an exception for when something is misconfigured for a use
     *
     * @param string $type
     * @param string $name
     * @param string $subject
     *
     * @return self
     */
    public static function misconfigured(string $type, string $name, string $subject): self
    {
        return new self('The current ' . $type . ' [' . $name . '] is not configured correctly for ' . $subject);
    }

    /**
     * Create an exception for when an expected configuration is not found
     *
     * @param string $type
     * @param string $name
     *
     * @return self
     */
    public static function notFound(string $type, string $name): self
    {
        return new self('The ' . $type . ' for [' . $name . '] could not be found');
    }

    /**
     * Create a new exception for when a default value is missing
     *
     * @param string $type
     *
     * @return self
     */
    public static function noDefault(string $type): self
    {
        return new self('There is no default ' . $type . ' set');
    }

    /**
     * Create a new exception for when a resolution hook is not supported
     *
     * @param \Sprout\Core\Support\ResolutionHook $hook
     *
     * @return self
     */
    public static function unsupportedHook(ResolutionHook $hook): self
    {
        return new self('The resolution hook [' . $hook->name . '] is not supported');
    }
}
