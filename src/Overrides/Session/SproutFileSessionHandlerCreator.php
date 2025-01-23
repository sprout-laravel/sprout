<?php
declare(strict_types=1);

namespace Sprout\Overrides\Session;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Sprout;

final class SproutFileSessionHandlerCreator
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
     * Create the tenant-aware session file driver
     *
     * @return \Sprout\Overrides\Session\SproutFileSessionHandler
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __invoke(): SproutFileSessionHandler
    {
        /** @var string $originalPath */
        $originalPath = config('session.files');
        $path         = rtrim($originalPath, '/');

        /** @var int $lifetime */
        $lifetime = config('session.lifetime');

        $handler = new SproutFileSessionHandler(
            $this->app->make('files'),
            $path,
            $lifetime
        );

        if ($this->sprout->withinContext()) {
            $tenancy = $this->sprout->getCurrentTenancy();

            $handler->setTenancy($tenancy)
                    ->setTenant($tenancy?->tenant());
        }

        return $handler;
    }
}
