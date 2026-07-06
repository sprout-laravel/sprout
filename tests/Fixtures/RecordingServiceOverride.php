<?php
declare(strict_types=1);

namespace Sprout\Tests\Fixtures;

use Sprout\Contracts\ServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

/**
 * A test double that records whether the manager delegated setup/cleanup to it.
 */
class RecordingServiceOverride implements ServiceOverride
{
    public bool $wasSetUp = false;

    public bool $wasCleanedUp = false;

    public function __construct(string $service, array $config)
    {
        //
    }

    public function setup(Tenancy $tenancy, Tenant $tenant): void
    {
        $this->wasSetUp = true;
    }

    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        $this->wasCleanedUp = true;
    }
}
