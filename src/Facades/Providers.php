<?php
declare(strict_types=1);

namespace Sprout\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Sprout\Contracts\TenantProvider;
use Sprout\Managers\TenantProviderManager;

/**
 * Providers Facade
 *
 * This is the facade for the {@see \Sprout\Managers\TenantProviderManager} class.
 *
 * @method static TenantProviderManager flushResolved()
 * @method static TenantProvider get(string|null $name = null)
 * @method static string getConfigKey(string $name)
 * @method static string getDefaultName()
 * @method static string getFactoryName()
 * @method static bool hasDriver(string $name)
 * @method static bool hasResolved(string|null $name)
 * @method static void register(string $name, Closure $creator)
 */
final class Providers extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TenantProviderManager::class;
    }
}
