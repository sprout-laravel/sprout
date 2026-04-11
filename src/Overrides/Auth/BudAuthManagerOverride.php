<?php
declare(strict_types=1);

namespace Sprout\Overrides\Auth;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Overrides\BaseOverride;
use Sprout\Sprout;

final class BudAuthManagerOverride extends BaseOverride implements BootableServiceOverride
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
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        $original = null;

        // If the auth manager has already been resolved
        if ($app->resolved('auth')) {
            // We'll grab the manager
            $original = $app->make('auth');
            // and then tell the container to forget it
            $app->forgetInstance('auth');
        }

        // Bind a replacement auth manager to enable Bud features
        $app->singleton('auth', fn (Application $app) => new BudAuthManager($app, $original));
    }
}
