<?php
declare(strict_types=1);

namespace Sprout\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Sprout\Contracts\IdentityResolver;
use Sprout\Managers\IdentityResolverManager;

/**
 * Identity Resolvers Facade
 *
 * This is the facade for the {@see \Sprout\Managers\IdentityResolverManager} class.
 *
 * @method static IdentityResolverManager flushResolved()
 * @method static IdentityResolver get(string|null $name = null)
 * @method static string getConfigKey(string $name)
 * @method static string getDefaultName()
 * @method static string getFactoryName()
 * @method static bool hasDriver(string $name)
 * @method static bool hasResolved(string|null $name)
 * @method static void register(string $name, Closure $creator)
 */
final class Resolvers extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IdentityResolver::class;
    }
}
