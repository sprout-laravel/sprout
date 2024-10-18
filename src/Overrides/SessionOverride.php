<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\SessionManager;
use RuntimeException;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\TenantMissing;
use Sprout\Overrides\Session\DatabaseSessionHandler;
use Sprout\Sprout;
use function Sprout\sprout;

final class SessionOverride implements BootableServiceOverride
{
    private static ?string $path = null;

    private static ?string $domain = null;

    private static ?bool $secure = null;

    private static ?string $sameSite = null;

    private static bool $overrideDatabase = true;

    public static function setDomain(?string $domain): void
    {
        self::$domain = $domain;
    }

    public static function setPath(?string $path): void
    {
        self::$path = $path;
    }

    // @codeCoverageIgnoreStart
    public static function setSameSite(?string $sameSite): void
    {
        self::$sameSite = $sameSite;
    }

    public static function setSecure(?bool $secure): void
    {
        self::$secure = $secure;
    }
    // @codeCoverageIgnoreEnd

    public static function doNotOverrideDatabase(): void
    {
        self::$overrideDatabase = false;
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

        if (self::$overrideDatabase) {
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
        // Collect the values
        $path     = self::$path ?? config('session.path') ?? '/';
        $domain   = self::$domain ?? config('session.domain');
        $secure   = self::$secure ?? config('session.secure', false);
        $sameSite = self::$sameSite ?? config('session.same_site');

        /**
         * This is here to make PHPStan quiet down
         *
         * @var string      $path
         * @var string|null $domain
         * @var bool|null   $secure
         * @var string|null $sameSite
         */

        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = config();

        // Set the config values
        $config->set('session.path', $path);
        $config->set('session.domain', $domain);
        $config->set('session.secure', $secure);
        $config->set('session.same_site', $sameSite);
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
            $tenancy      = sprout()->getCurrentTenancy();

            if ($tenancy === null) {
                throw new RuntimeException('No current tenancy');
            }

            // If there's no tenant, error out
            if (! $tenancy->check()) {
                throw TenantMissing::make($tenancy->getName());
            }

            $tenant = $tenancy->tenant();

            // If the tenant isn't configured for resources, also error out
            if (! ($tenant instanceof TenantHasResources)) {
                // TODO: Better exception
                throw new RuntimeException('Current tenant isn\t configured for resources');
            }

            $path .= $tenant->getTenantResourceKey();

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
        return static function (): DatabaseSessionHandler {
            $table      = config('session.table');
            $lifetime   = config('session.lifetime');
            $connection = config('session.connection');

            /**
             * @var string|null $connection
             * @var string      $table
             * @var int         $lifetime
             */

            return new DatabaseSessionHandler(
                app()->make('db')->connection($connection),
                $table,
                $lifetime,
                app()
            );
        };
    }
}
