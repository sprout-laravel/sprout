<?php

namespace Sprout\Contracts;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Sprout;

/**
 * Bootable Service Override
 *
 * This contract marks a {@see ServiceOverride} as being
 * bootable, meaning that it can perform actions during the boot stage of the
 * framework.
 */
interface BootableServiceOverride extends ServiceOverride
{
    /**
     * Boot a service override
     *
     * This method should perform any initial steps required for the service
     * override that take place during the booting of the framework.
     *
     * @param Application $app
     * @param Sprout      $sprout
     *
     * @return void
     */
    public function boot(Application $app, Sprout $sprout): void;
}
