<?php
declare(strict_types=1);

namespace Sprout\Core\Overrides;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Arr;
use Sprout\Core\Contracts\BootableServiceOverride;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Overrides\Session\SproutDatabaseSessionHandlerCreator;
use Sprout\Core\Overrides\Session\SproutFileSessionHandlerCreator;
use Sprout\Core\Sprout;
use Sprout\Core\Support\Settings;

/**
 * Session Override
 *
 * This class provides the override/multitenancy extension/features for Laravels
 * session service.
 *
 * @package Overrides
 */
final class SessionOverride extends BaseOverride implements BootableServiceOverride
{
    /**
     * Boot a service override
     *
     * This method should perform any initial steps required for the service
     * override that take place during the booting of the framework.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Sprout\Core\Sprout                          $sprout
     *
     * @return void
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        $this->setApp($app)->setSprout($sprout);

        // If the session manager has been resolved, we can add the driver
        if ($app->resolved('session')) {
            $manager = $app->make('session');
            $this->addDriver($manager, $app, $sprout);
            $manager->forgetDrivers();
        } else {
            // But if it hasn't, we'll add it once it is
            $app->afterResolving('session', function (SessionManager $manager) use ($app, $sprout) {
                $this->addDriver($manager, $app, $sprout);
            });
        }
    }

    protected function addDriver(SessionManager $manager, Application $app, Sprout $sprout): void
    {
        $creator = new SproutFileSessionHandlerCreator($app, $sprout);

        $manager->extend('file', $creator(...));
        $manager->extend('native', $creator(...));

        /** @var bool $overrideDatabase */
        $overrideDatabase = $this->config['database'] ?? true;

        if ($sprout->settings()->shouldNotOverrideTheDatabase($overrideDatabase) === false) {
            $manager->extend('database', (new SproutDatabaseSessionHandlerCreator($app, $sprout))(...));
        }
    }

    /**
     * Set up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when a new tenant is marked as the current tenant.
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                         $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, Tenant $tenant): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config   = $this->getApp()->make('config');
        $settings = $this->getSprout()->settings();

        if (empty($settings->array('original.session', []))) {
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
            $config->set('session.same_site', $settings->getCookieSameSite());
        }

        $config->set('session.cookie', $this->getCookieName($tenancy, $tenant));

        $this->refreshSessionStore();
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
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                         $tenant
     *
     * @return void
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config   = $this->getApp()->make('config');
        $settings = $this->getSprout()->settings();

        /** @var array<string, mixed> $original */
        $original = $settings->array('original.session', []);

        if (! empty($original)) {
            $config->set('session.path', $original['path']);
            $config->set('session.domain', $original['domain']);

            if (array_key_exists('secure', $original)) {
                $config->set('session.secure', $original['secure']);
            }

            if (array_key_exists('same_site', $original)) {
                $config->set('session.same_site', $original['same_site']);
            }

            $settings->set('original.session', []);
        }

        $this->refreshSessionStore();
    }

    /**
     * @param \Sprout\Core\Contracts\Tenancy<*> $tenancy
     * @param \Sprout\Core\Contracts\Tenant     $tenant
     *
     * @return string
     */
    private function getCookieName(Tenancy $tenancy, Tenant $tenant): string
    {
        return $tenancy->getName() . '_' . $tenant->getTenantIdentifier() . '_session';
    }

    /**
     * Set the tenant details and refresh the session
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    private function refreshSessionStore(): void
    {
        // We only want to touch this if the session manager has actually been
        // loaded, and is therefore most likely being used
        if ($this->getApp()->resolved('session')) {
            $manager = $this->getApp()->make('session');

            // If there are no loaded drivers, we can exit early
            if (empty($manager->getDrivers())) {
                return;
            }

            // We need to forget the driver, so that they can be reloaded
            // with new session data
            $manager->forgetDrivers();
        }
    }
}
