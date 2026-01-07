<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides;

use Closure;
use RuntimeException;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\Mailer\BudMailerTransportCreator;
use Sprout\Sprout;

/**
 * Mailer Override
 *
 * This override specifically allows for the creation of mailers
 * using Bud config stores.
 *
 * @extends \Sprout\Bud\Overrides\BaseOverride<\Illuminate\Mail\MailManager>
 */
final class MailerOverride extends BaseOverride
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
     * @param object                               $service
     * @param \Sprout\Bud\Bud                      $bud
     * @param \Sprout\Sprout                       $sprout
     * @param \Closure                             $tracker
     *
     * @phpstan-param \Illuminate\Mail\MailManager $service
     *
     * @return void
     */
    protected function addDriver(object $service, Bud $bud, Sprout $sprout, Closure $tracker): void
    {
        // Add a bud driver.
        $service->extend('bud', function ($config) use ($service, $bud, $sprout, $tracker) {
            /**
             * @var array<string, mixed>&array{budStore?:string|null,name?:mixed} $config
             */

            if (! isset($config['name']) || ! is_string($config['name']) || $config['name'] === '') {
                throw new RuntimeException('Cannot create a mailer using bud without a name'); // @codeCoverageIgnore
            }

            // Track the mailer name.
            $tracker($config['name']);

            return (new BudMailerTransportCreator($service, $bud, $sprout, $config['name'], $config))();
        });
    }

    /**
     * Clean-up an overridden service.
     *
     * @param object                               $service
     * @param string                               $name
     *
     * @phpstan-param \Illuminate\Mail\MailManager $service
     *
     * @return void
     */
    protected function cleanupOverride(object $service, string $name): void
    {
        $service->purge($name);
    }
}
