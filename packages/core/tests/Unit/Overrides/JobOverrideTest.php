<?php
declare(strict_types=1);

namespace Sprout\Core\Tests\Unit\Overrides;

use Illuminate\Config\Repository;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Core\Contracts\BootableServiceOverride;
use Sprout\Core\Listeners\SetCurrentTenantForJob;
use Sprout\Core\Overrides\JobOverride;
use Sprout\Core\Overrides\SessionOverride;
use Sprout\Core\Tests\Unit\UnitTestCase;
use function Sprout\Core\sprout;

class JobOverrideTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function (Repository $config) {
            $config->set('sprout.overrides', []);
        });
    }

    #[Test]
    public function isBuiltCorrectly(): void
    {
        $this->assertTrue(is_subclass_of(SessionOverride::class, BootableServiceOverride::class));
    }

    #[Test]
    public function isRegisteredWithSproutCorrectly(): void
    {
        $sprout = sprout();

        config()->set('sprout.overrides', [
            'job' => [
                'driver' => JobOverride::class,
            ],
        ]);

        $sprout->overrides()->registerOverrides();

        $this->assertTrue($sprout->overrides()->hasOverride('job'));
        $this->assertSame(JobOverride::class, $sprout->overrides()->getOverrideClass('job'));
        $this->assertTrue($sprout->overrides()->isOverrideBootable('job'));
        $this->assertTrue($sprout->overrides()->hasOverrideBooted('job'));
    }

    #[Test]
    public function isBootedCorrectly(): void
    {
        $sprout = sprout();

        Event::fake();

        config()->set('sprout.overrides', [
            'job' => [
                'driver' => JobOverride::class,
            ],
        ]);

        $sprout->overrides()->registerOverrides();

        Event::assertListening(JobProcessing::class, SetCurrentTenantForJob::class);
    }
}
