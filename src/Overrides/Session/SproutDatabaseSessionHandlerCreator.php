<?php
declare(strict_types=1);

namespace Sprout\Overrides\Session;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Sprout;

final class SproutDatabaseSessionHandlerCreator
{
    /**
     * @var Application
     */
    private Application $app;

    /**
     * @var Sprout
     */
    private Sprout $sprout;

    /**
     * Create a new instance
     *
     * @param Application $app
     */
    public function __construct(Application $app, Sprout $sprout)
    {
        $this->app    = $app;
        $this->sprout = $sprout;
    }

    /**
     * Create the tenant-aware session database driver
     *
     * @return SproutDatabaseSessionHandler
     *
     * @throws BindingResolutionException
     */
    public function __invoke(): SproutDatabaseSessionHandler
    {
        $table      = config('session.table');
        $lifetime   = config('session.lifetime');
        $connection = config('session.connection');

        /**
         * @var string|null $connection
         * @var string      $table
         * @var int         $lifetime
         */
        $handler = new SproutDatabaseSessionHandler(
            $this->app->make('db')->connection($connection),
            $table,
            $lifetime,
            $this->app,
        );

        if ($this->sprout->withinContext()) {
            /** @var Tenancy<Tenant>|null $tenancy */
            $tenancy = $this->sprout->getCurrentTenancy();

            $handler->setTenancy($tenancy)
                    ->setTenant($tenancy?->tenant());
        }

        return $handler;
    }
}
