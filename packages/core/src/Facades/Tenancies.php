<?php
declare(strict_types=1);

namespace Sprout\Core\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Managers\TenancyManager;

/**
 * Tenancies Facade
 *
 * This is the facade for the {@see \Sprout\Core\Managers\TenancyManager} class.
 *
 * @method static TenancyManager flushResolved()
 * @method static Tenancy get(string|null $name = null)
 * @method static string getConfigKey(string $name)
 * @method static string getDefaultName()
 * @method static string getFactoryName()
 * @method static bool hasDriver(string $name)
 * @method static bool hasResolved(string|null $name)
 * @method static void register(string $name, Closure $creator)
 *
 * @phpstan-ignore missingType.generics
 */
final class Tenancies extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TenancyManager::class;
    }
}
