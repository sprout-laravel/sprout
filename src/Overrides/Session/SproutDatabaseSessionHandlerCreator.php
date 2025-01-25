<?php
declare(strict_types=1);

namespace Sprout\Overrides\Session;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Sprout;

final class SproutDatabaseSessionHandlerCreator
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private Application $app;

    /**
     * @var \Sprout\Sprout
     */
    private Sprout $sprout;

    /**
     * Create a new instance
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct(Application $app, Sprout $sprout)
    {
        $this->app    = $app;
        $this->sprout = $sprout;
    }

    /**
     * Create the tenant-aware session database driver
     *
     * @return \Sprout\Overrides\Session\SproutDatabaseSessionHandler
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
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
            $this->app
        );

        if ($this->sprout->withinContext()) {
            $tenancy = $this->sprout->getCurrentTenancy();

            $handler->setTenancy($tenancy)
                    ->setTenant($tenancy?->tenant());
        }

        return $handler;
    }
}
