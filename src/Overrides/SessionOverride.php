<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Session\DatabaseSessionHandler as OriginalDatabaseSessionHandler;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Arr;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissing;
use Sprout\Exceptions\TenantMissing;
use Sprout\Overrides\Session\TenantAwareDatabaseSessionHandler;
use Sprout\Sprout;
use Sprout\Support\Settings;
use function Sprout\settings;
use function Sprout\sprout;

/**
 * Session Override
 *
 * This class provides the override/multitenancy extension/features for Laravels
 * session service.
 *
 * @package Overrides
 */
final class SessionOverride implements BootableServiceOverride, DeferrableServiceOverride
{
    /**
     * Get the service to watch for before overriding
     *
     * @return string
     */
    public static function service(): string
    {
        return SessionManager::class;
    }

    /**
     * Boot a service override
     *
     * This method should perform any initial steps required for the service
     * override that take place during the booting of the framework.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Sprout\Sprout                               $sprout
     *
     * @return void
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        $sessionManager = app(SessionManager::class);

        // The native driver proxies the call to the createFileDriver method,
        // so we have to override that too.
        $fileCreator = self::createFilesDriver();

        $sessionManager->extend('file', $fileCreator);
        $sessionManager->extend('native', $fileCreator);

        if (settings()->shouldNotOverrideTheDatabase(false) === false) {
            $sessionManager->extend('database', self::createDatabaseDriver());
        }
    }

    /**
     * Set up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when a new tenant is marked as the current tenant.
     *
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, Tenant $tenant): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config   = config();
        $settings = settings();

        if (! $settings->has('original.session')) {
            /** @var array<string, mixed> $original */
            $original = $config->get('session');
            $settings->set(
                'original.session',
                Arr::only($original, ['path', 'domain', 'secure', 'same_site'])
            );
        }

        if ($settings->has(Settings::URL_PATH)) {
            $config->set('session.path', $settings->getUrlPath());
        }

        if ($settings->has(Settings::URL_DOMAIN)) {
            $config->set('session.domain', $settings->getUrlDomain());
        }

        if ($settings->has(Settings::COOKIE_SECURE)) {
            $config->set('session.secure', $settings->shouldCookieBeSecure());
        }

        if ($settings->has(Settings::COOKIE_SAME_SITE)) {
            $config->set('session.same_site', $settings->shouldCookeBeSameSite());
        }

        $config->set('session.cookie', $this->getCookieName($tenancy, $tenant));

        // Reset all the drivers
        app(SessionManager::class)->forgetDrivers();
    }

    /**
     * Clean up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when the current tenant is unset, either to be replaced
     * by another tenant, or none.
     *
     * It will be called before {@see self::setup()}, but only if the previous
     * tenant was not null.
     *
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return void
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        // Reset all the drivers
        app(SessionManager::class)->forgetDrivers();
    }

    /**
     * @param \Sprout\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Contracts\Tenant $tenant
     *
     * @return string
     */
    private function getCookieName(Tenancy $tenancy, Tenant $tenant): string
    {
        return $tenancy->getName() . '_' . $tenant->getTenantIdentifier() . '_session';
    }

    /**
     * Get a creator for a tenant scoped file session handler
     *
     * @return \Closure
     */
    private static function createFilesDriver(): Closure
    {
        return static function (): FileSessionHandler {
            /** @var string $originalPath */
            $originalPath = config('session.files');
            $path         = rtrim($originalPath, '/') . DIRECTORY_SEPARATOR;

            if (sprout()->withinContext()) {
                $tenancy = sprout()->getCurrentTenancy();

                if ($tenancy === null) {
                    throw TenancyMissing::make();
                }

                // If there's no tenant, error out
                if (! $tenancy->check()) {
                    throw TenantMissing::make($tenancy->getName());
                }

                $tenant = $tenancy->tenant();

                // If the tenant isn't configured for resources, also error out
                if (! ($tenant instanceof TenantHasResources)) {
                    throw MisconfigurationException::misconfigured('tenant', $tenant::class, 'resources');
                }

                $path .= $tenant->getTenantResourceKey();
            }

            /** @var int $lifetime */
            $lifetime = config('session.lifetime');

            return new FileSessionHandler(
                app()->make('files'),
                $path,
                $lifetime,
            );
        };
    }

    private static function createDatabaseDriver(): Closure
    {
        return static function (): OriginalDatabaseSessionHandler {
            $table      = config('session.table');
            $lifetime   = config('session.lifetime');
            $connection = config('session.connection');

            /**
             * @var string|null $connection
             * @var string      $table
             * @var int         $lifetime
             */

            if (sprout()->withinContext()) {
                return new TenantAwareDatabaseSessionHandler(
                    app()->make('db')->connection($connection),
                    $table,
                    $lifetime,
                    app()
                );
            }

            return new OriginalDatabaseSessionHandler(
                app()->make('db')->connection($connection),
                $table,
                $lifetime,
                app()
            );
        };
    }
}
