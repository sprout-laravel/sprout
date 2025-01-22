<?php
declare(strict_types=1);

namespace Sprout\Overrides\Session;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Session\FileSessionHandler;
use Sprout\Sprout;

final class SproutSessionFileDriverCreator
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
     * @return \Illuminate\Session\FileSessionHandler
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __invoke(): FileSessionHandler
    {
        /** @var string $originalPath */
        $originalPath = config('session.files');
        $path         = rtrim($originalPath, '/') . DIRECTORY_SEPARATOR;

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
