<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Attributes;

use Illuminate\Config\Repository;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Attributes\CurrentTenant;
use Sprout\Attributes\Override;
use Sprout\Contracts\ServiceOverride;
use Sprout\Managers\ServiceOverrideManager;
use Sprout\Support\GenericTenant;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;
use function Sprout\tenancy;

class OverrideTest extends UnitTestCase
{
    #[Test]
    public function resolvesServiceOverrides(): void
    {
        $manager = $this->app->make(ServiceOverrideManager::class);

        $callback1 = static function (#[Override('session')] ServiceOverride $override) {
            return $override;
        };

        $callback2 = static function (#[Override('filesystem')] ServiceOverride $override) {
            return $override;
        };

        $this->assertSame($manager->get('session'), $this->app->call($callback1));
        $this->assertSame($manager->get('filesystem'), $this->app->call($callback2));
    }
}
