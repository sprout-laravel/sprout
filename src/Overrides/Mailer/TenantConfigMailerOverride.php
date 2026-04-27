<?php
declare(strict_types=1);

namespace Sprout\Overrides\Mailer;

use Closure;
use Illuminate\Mail\MailManager;
use RuntimeException;
use Sprout\Overrides\TenantConfigBaseOverride;
use Sprout\Sprout;
use Sprout\TenantConfig;

/**
 * Mailer Override
 *
 * This override specifically allows for the creation of mailers
 * using tenant config stores.
 *
 * @extends TenantConfigBaseOverride<MailManager>
 */
final class TenantConfigMailerOverride extends TenantConfigBaseOverride
{
    /**
     * Get the name of the service being overridden.
     *
     * @return string
     */
    protected function serviceName(): string
    {
        return 'mail.manager';
    }

    /**
     * Add a driver to the service.
     *
     * @param object       $service
     * @param TenantConfig $tenantConfig
     * @param Sprout       $sprout
     * @param Closure      $tracker
     *
     * @phpstan-param MailManager $service
     *
     * @return void
     */
    protected function addDriver(object $service, TenantConfig $tenantConfig, Sprout $sprout, Closure $tracker): void
    {
        // Add a tenant config driver.
        $service->extend('sprout:config', function ($config) use ($service, $tenantConfig, $sprout, $tracker) {
            /**
             * @var array<string, mixed>&array{configStore?:string|null,name?:mixed} $config
             */
            if (! isset($config['name']) || ! is_string($config['name']) || $config['name'] === '') {
                throw new RuntimeException('Cannot create a mailer using tenant config without a name'); // @codeCoverageIgnore
            }

            // Track the mailer name.
            $tracker($config['name']);

            return (new TenantConfigMailerTransportCreator($service, $tenantConfig, $sprout, $config['name'], $config))();
        });
    }

    /**
     * Clean-up an overridden service.
     *
     * @param object $service
     * @param string $name
     *
     * @phpstan-param MailManager $service
     *
     * @return void
     */
    protected function cleanupOverride(object $service, string $name): void
    {
        $service->purge($name);
    }
}
