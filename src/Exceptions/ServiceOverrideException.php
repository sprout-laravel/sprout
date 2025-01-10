<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

use Sprout\Contracts\ServiceOverride;

final class ServiceOverrideException extends SproutException
{
    /**
     * Create an exception when a provided service override class is invalid
     *
     * @param class-string $class
     *
     * @return self
     */
    public static function invalidClass(string $class): self
    {
        return new self('The provided service override [' . $class . '] does not implement the ' . ServiceOverride::class . ' interface');
    }

    /**
     * Create an exception when attempting to replace a service override that has already been processed
     *
     * @param string                                          $service
     * @param class-string<\Sprout\Contracts\ServiceOverride> $class
     *
     * @return self
     */
    public static function alreadyProcessed(string $service, string $class): self
    {
        return new self(
            'The service [' . $service . '] already has an override registered [' . $class . '] which has already been processed'
        );
    }
}
