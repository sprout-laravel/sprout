<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Illuminate\Config\Repository;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\DeferrableServiceOverride;
use Sprout\Listeners\SetCurrentTenantForJob;
use Sprout\Overrides\JobOverride;
use Sprout\Support\Services;
use Sprout\Tests\Unit\UnitTestCase;
use function Sprout\sprout;

class JobOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.services', []);
        });
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(JobOverride::class, BootableServiceOverride::class));
        $this->assertFalse(is_subclass_of(JobOverride::class, DeferrableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        $sprout->registerOverride(Services::JOB, JobOverride::class);

        $this->assertTrue($sprout->hasRegisteredOverride(JobOverride::class));
        $this->assertTrue($sprout->isBootableOverride(JobOverride::class));
        $this->assertFalse($sprout->isDeferrableOverride(JobOverride::class));
    }

    #[Test]
    public function isBootedCorrectly(): void
    {
        $sprout = sprout();

        Event::fake();

        $sprout->registerOverride(Services::JOB, JobOverride::class);

        Event::assertListening(JobProcessing::class, SetCurrentTenantForJob::class);
    }
}
