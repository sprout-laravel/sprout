<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Managers;

use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Managers\TenancyManager;
use Sprout\Providers\EloquentTenantProvider;
use Sprout\Support\DefaultTenancy;
use Sprout\Tests\Unit\UnitTestCase;
use Workbench\App\Models\NoResourcesTenantModel;
use function Sprout\sprout;

class TenancyManagerTest extends UnitTestCase
{
    protected function withoutDefault($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.tenancy', null);
        });
    }

    protected function withoutConfig($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.tenancies.fake', null);
        });
    }

    #[Test]
    public function isNamedCorrectly(): void
    {
        $manager = sprout()->tenancies();

        $this->assertSame('tenancy', $manager->getFactoryName());
    }

    #[Test]
    public function getsTheDefaultNameFromTheConfig(): void
    {
        $manager = sprout()->tenancies();

        $this->assertSame('tenants', $manager->getDefaultName());

        config()->set('multitenancy.defaults.tenancy', 'backup');

        $this->assertSame('backup', $manager->getDefaultName());
    }

    #[Test]
    public function generatesConfigKeys(): void
    {
        $manager = sprout()->tenancies();

        $this->assertSame('multitenancy.tenancies.test-config', $manager->getConfigKey('test-config'));
    }

    #[Test]
    public function errorsIfTheresNoConfigCanBeFoundForADriver(): void
    {
        $manager = sprout()->tenancies();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The config for [tenancy::missing] could not be found');

        $manager->get('missing');
    }

    #[Test]
    public function errorsIfTheresNoCreatorForADriver(): void
    {
        $manager = sprout()->tenancies();

        config()->set('multitenancy.tenancies.missing', ['driver' => 'missing']);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The creator for [tenancy::missing] could not be found');

        $manager->get('missing');
    }

    #[Test, DefineEnvironment('withoutDefault')]
    public function errorsIfTheresNoDefault(): void
    {
        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('There is no default tenancy set');

        $manager = sprout()->tenancies();

        $manager->get();
    }

    #[Test]
    public function allowsCustomCreators(): void
    {
        config()->set('multitenancy.tenancies.tenants.driver', 'hello-there');

        TenancyManager::register('hello-there', static function () {
            return new DefaultTenancy('hello-there', sprout()->providers()->get(), []);
        });

        $manager = sprout()->tenancies();

        $this->assertTrue($manager->hasDriver('hello-there'));
        $this->assertFalse($manager->hasResolved('tenants'));
        $this->assertFalse($manager->hasResolved('fake'));

        $provider = $manager->get('tenants');

        $this->assertInstanceOf(DefaultTenancy::class, $provider);
        $this->assertSame('hello-there', $provider->getName());
        $this->assertTrue($manager->hasResolved('tenants'));
        $this->assertFalse($manager->hasResolved('false'));
    }
}
