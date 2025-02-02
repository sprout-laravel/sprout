<?php
declare(strict_types=1);

namespace Sprout\Overrides;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\Filesystem\SproutFilesystemDriverCreator;
use Sprout\Overrides\Filesystem\SproutFilesystemManager;
use Sprout\Sprout;

final class FilesystemManagerOverride extends BaseOverride implements BootableServiceOverride
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

        // If the filesystem has already been resolved
        if ($app->resolved('filesystem')) {
            // We'll grab the manager
            $original = $app->make('filesystem');
            // and then tell the container to forget it
            $app->forgetInstance('filesystem');
        }

        // Bind a replacement filesystem manager to enable Sprout features
        $app->singleton('filesystem', fn ($app) => new SproutFilesystemManager($app, $original));
    }
}
