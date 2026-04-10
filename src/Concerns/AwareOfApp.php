<?php
declare(strict_types=1);

namespace Sprout\Concerns;

use Illuminate\Contracts\Foundation\Application;

trait AwareOfApp
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private Application $app;

    public function setApp(Application $app): static
    {
        $this->app = $app;

        return $this;
    }

    public function getApp(): Application
    {
        return $this->app;
    }
}
