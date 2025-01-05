<?php
declare(strict_types=1);

namespace Sprout\Facades;

use Illuminate\Support\Facades\Facade;
use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\TenantProviderManager;
use Sprout\Support\ResolutionHook;

/**
 * Sprout Facade
 *
 * @method static void bootOverrides()
 * @method static void cleanupOverrides(Tenancy $tenancy, Tenant $tenant)
 * @method static mixed config(string $key, mixed $default = null)
 * @method static array<Tenancy> getAllCurrentTenancies()
 * @method static array<ServiceOverride> getCurrentOverrides(?Tenancy $tenancy = null)
 * @method static Tenancy|null getCurrentTenancy()
 * @method static array<string, ServiceOverride> getOverrides()
 * @method static array<string> getRegisteredOverrides()
 * @method static bool hasBootedOverride(string $class)
 * @method static bool hasCurrentTenancy()
 * @method static bool hasOverride(string $class)
 * @method static bool hasRegisteredOverride(string $class)
 * @method static bool hasSetupOverride(Tenancy $tenancy, string $class)
 * @method static bool haveOverridesBooted()
 * @method static bool isBootableOverride(string $class)
 * @method static \Sprout\Sprout markAsInContext()
 * @method static \Sprout\Sprout markAsOutsideContext()
 * @method static TenantProviderManager providers()
 * @method static \Sprout\Sprout registerOverride(string $class)
 * @method static IdentityResolverManager resolvers()
 * @method static string route(string $name, Tenant $tenant, string|null $resolver = null, string|null $tenancy = null, array $parameters = [], bool $absolute = true)
 * @method static void setCurrentTenancy(Tenancy $tenancy)
 * @method static void setupOverrides(Tenancy $tenancy, Tenant $tenant)
 * @method static bool supportsHook(ResolutionHook $hook)
 * @method static \Sprout\Managers\TenancyManager tenancies()
 * @method static bool withinContext()
 */
final class Sprout extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sprout\Sprout::class;
    }
}
