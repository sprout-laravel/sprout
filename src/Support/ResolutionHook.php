<?php
declare(strict_types=1);

namespace Sprout\Support;

/**
 * Resolution Hook
 *
 * This enum is used as a way of identifying the various points within the
 * Laravel request lifecycle where tenants can be resolved.
 *
 * @package Core
 */
enum ResolutionHook
{
    /**
     * During the booting of service providers
     */
    case Booting;

    /**
     * During the route resolution
     */
    case Routing;

    /**
     * During the middleware stack
     */
    case Middleware;
}
