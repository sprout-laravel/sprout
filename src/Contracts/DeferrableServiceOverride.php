<?php

namespace Sprout\Contracts;

/**
 * Deferrable Service Override
 *
 * Deferrable service overrides are overrides that should have their booting, or
 * setup, deferred until after a dependent service is resolved within
 * Laravels container.
 */
interface DeferrableServiceOverride extends ServiceOverride
{
    /**
     * Get the service to watch for before overriding
     *
     * @return string|class-string
     */
    public static function service(): string;
}
