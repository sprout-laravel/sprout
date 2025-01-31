<?php
declare(strict_types=1);

namespace Sprout\Facades;

use Illuminate\Support\Facades\Facade;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\ServiceOverrideManager;
use Sprout\Managers\TenancyManager;
use Sprout\Managers\TenantProviderManager;
use Sprout\Support\ResolutionHook;
use Sprout\Support\SettingsRepository;

/**
 * Sprout Facade
 *
 * @method static mixed config(string $key, mixed $default = null)
 * @method static array<Tenancy> getAllCurrentTenancies()
 * @method static ResolutionHook|null getCurrentHook()
 * @method static Tenancy|null getCurrentTenancy()
 * @method static bool hasCurrentTenancy()
 * @method static bool isCurrentHook(ResolutionHook|null $hook)
 * @method static \Sprout\Sprout markAsInContext()
 * @method static \Sprout\Sprout markAsOutsideContext()
 * @method static ServiceOverrideManager overrides()
 * @method static TenantProviderManager providers()
 * @method static \Sprout\Sprout resetTenancies()
 * @method static IdentityResolverManager resolvers()
 * @method static string route(string $name, Tenant $tenant, string|null $resolver = null, string|null $tenancy = null, array $parameters = [], bool $absolute = true)
 * @method static \Sprout\Sprout setCurrentHook(ResolutionHook|null $hook)
 * @method static void setCurrentTenancy(Tenancy $tenancy)
 * @method static mixed setting(string $key, mixed $default = null)
 * @method static SettingsRepository settings()
 * @method static bool supportsHook(ResolutionHook $hook)
 * @method static TenancyManager tenancies()
 * @method static bool withinContext()
 */
final class Sprout extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sprout\Sprout::class;
    }
}
