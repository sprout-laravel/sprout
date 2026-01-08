<?php

namespace Sprout\Core\Contracts;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Core\Sprout;

/**
 * Bootable Service Override
 *
 * This contract marks a {@see \Sprout\Core\Contracts\ServiceOverride} as being
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
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Sprout\Core\Sprout                          $sprout
     *
     * @return void
     */
    public function boot(Application $app, Sprout $sprout): void;
}
