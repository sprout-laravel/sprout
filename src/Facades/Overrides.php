<?php
declare(strict_types=1);

namespace Sprout\Core\Facades;

use Illuminate\Support\Facades\Facade;
use Sprout\Core\Contracts\ServiceOverride;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Managers\ServiceOverrideManager;

/**
 * Service Override Facade
 *
 * This is the facade for the {@see \Sprout\Core\Managers\ServiceOverrideManager} class.
 *
 * @method static void bootOverrides()
 * @method static void cleanupOverrides(Tenancy $tenancy, Tenant $tenant)
 * @method static ServiceOverride|null get(string $service)
 * @method static string|null getOverrideClass(string $service)
 * @method static array getSetupOverrides(Tenancy $tenancy)
 * @method static bool hasOverride(string $service)
 * @method static bool hasOverrideBeenSetUp(string $service, ?Tenancy $tenancy = null)
 * @method static bool hasOverrideBooted(string $service)
 * @method static bool hasTenancyBeenSetup(?Tenancy $tenancy = null)
 * @method static bool haveOverridesBooted()
 * @method static bool isOverrideBootable(string $service)
 * @method static void registerOverrides()
 * @method static void setupOverrides(Tenancy $tenancy, Tenant $tenant)
 *
 * @phpstan-ignore missingType.generics, missingType.generics, missingType.generics, missingType.generics, missingType.generics, missingType.iterableValue
 */
final class Overrides extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ServiceOverrideManager::class;
    }
}
