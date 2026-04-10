<?php
declare(strict_types=1);

namespace Sprout\Core\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Sprout\Core\Contracts\TenantProvider;
use Sprout\Core\Managers\TenantProviderManager;

/**
 * Providers Facade
 *
 * This is the facade for the {@see \Sprout\Core\Managers\TenantProviderManager} class.
 *
 * @method static TenantProviderManager flushResolved()
 * @method static TenantProvider get(string|null $name = null)
 * @method static string getConfigKey(string $name)
 * @method static string getDefaultName()
 * @method static string getFactoryName()
 * @method static bool hasDriver(string $name)
 * @method static bool hasResolved(string|null $name)
 * @method static void register(string $name, Closure $creator)
 *
 * @phpstan-ignore missingType.generics
 */
final class Providers extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TenantProviderManager::class;
    }
}
