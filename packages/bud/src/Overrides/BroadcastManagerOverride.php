<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Sprout\Bud\Overrides\Broadcast\BudBroadcastManager;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Overrides\BaseOverride;
use Sprout\Sprout;

final class BroadcastManagerOverride extends BaseOverride implements BootableServiceOverride
{
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
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        $original = null;

        // If the broadcast manager has already been resolved
        if ($app->resolved(BroadcastManager::class)) {
            // We'll grab the manager
            $original = $app->make(BroadcastManager::class);
            // and then tell the container to forget it
            $app->forgetInstance(BroadcastManager::class);
        }

        // Bind a replacement broadcast manager to enable Bud features
        $app->singleton(BroadcastManager::class, fn (Container $app) => new BudBroadcastManager($app, $original));
    }
}
