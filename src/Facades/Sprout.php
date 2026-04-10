<?php
declare(strict_types=1);

namespace Sprout\Core\Facades;

use Illuminate\Support\Facades\Facade;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Managers\IdentityResolverManager;
use Sprout\Core\Managers\ServiceOverrideManager;
use Sprout\Core\Managers\TenancyManager;
use Sprout\Core\Managers\TenantProviderManager;
use Sprout\Core\Support\ResolutionHook;
use Sprout\Core\Support\SettingsRepository;

/**
 * Sprout Facade
 *
 * This is the facade for the {@see \Sprout\Core\Sprout} class.
 *
 * @method static mixed config(string $key, mixed $default = null)
 * @method static array<Tenancy> getAllCurrentTenancies()
 * @method static ResolutionHook|null getCurrentHook()
 * @method static Tenancy|null getCurrentTenancy()
 * @method static bool hasCurrentTenancy()
 * @method static bool isCurrentHook(ResolutionHook|null $hook)
 * @method static \Sprout\Core\Sprout markAsInContext()
 * @method static \Sprout\Core\Sprout markAsOutsideContext()
 * @method static ServiceOverrideManager overrides()
 * @method static TenantProviderManager providers()
 * @method static \Sprout\Core\Sprout resetTenancies()
 * @method static IdentityResolverManager resolvers()
 * @method static string route(string $name, Tenant $tenant, string|null $resolver = null, string|null $tenancy = null, array<mixed> $parameters = [], bool $absolute = true)
 * @method static \Sprout\Core\Sprout setCurrentHook(ResolutionHook|null $hook)
 * @method static void setCurrentTenancy(Tenancy $tenancy)
 * @method static mixed setting(string $key, mixed $default = null)
 * @method static SettingsRepository settings()
 * @method static bool supportsHook(ResolutionHook $hook)
 * @method static TenancyManager tenancies()
 * @method static bool withinContext()
 *
 * @phpstan-ignore missingType.generics, missingType.generics, missingType.generics
 */
final class Sprout extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sprout\Core\Sprout::class;
    }
}
