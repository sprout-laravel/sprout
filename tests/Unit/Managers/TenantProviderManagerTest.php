<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Managers;

use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Managers\TenantProviderManager;
use Sprout\Providers\DatabaseTenantProvider;
use Sprout\Providers\EloquentTenantProvider;
use Sprout\Tests\Unit\UnitTestCase;
use stdClass;
use Workbench\App\Models\NoResourcesTenantModel;
use Workbench\App\Models\TenantModel;
use function Sprout\sprout;

class TenantProviderManagerTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.tenants.model', TenantModel::class);
            $config->set('multitenancy.providers.backup', ['driver' => 'database', 'table' => 'tenants']);
        });
    }

    protected function withoutDefault($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.provider', null);
        });
    }

    protected function withoutConfig($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.providers.database', null);
        });
    }

    #[Test]
    public function isNamedCorrectly(): void
    {
        $manager = sprout()->providers();

        $this->assertSame('provider', $manager->getFactoryName());
    }

    #[Test]
    public function getsTheDefaultNameFromTheConfig(): void
    {
        $manager = sprout()->providers();

        $this->assertSame('tenants', $manager->getDefaultName());

        config()->set('multitenancy.defaults.provider', 'backup');

        $this->assertSame('backup', $manager->getDefaultName());
    }

    #[Test]
    public function generatesConfigKeys(): void
    {
        $manager = sprout()->providers();

        $this->assertSame('multitenancy.providers.test-config', $manager->getConfigKey('test-config'));
    }

    #[Test]
    public function hasDefaultFirstPartyDrivers(): void
    {
        $manager = sprout()->providers();

        $this->assertFalse($manager->hasResolved());

        $this->assertTrue($manager->hasDriver('eloquent'));
        $this->assertTrue($manager->hasDriver('database'));

        $this->assertFalse($manager->hasResolved());

        $this->assertInstanceOf(EloquentTenantProvider::class, $manager->get('tenants'));
        $this->assertInstanceOf(DatabaseTenantProvider::class, $manager->get('backup'));

        $this->assertTrue($manager->hasResolved('tenants'));
        $this->assertTrue($manager->hasResolved('backup'));
    }

    #[Test]
    public function canFlushResolvedInstances(): void
    {
        $manager = sprout()->providers();

        $this->assertFalse($manager->hasResolved());

        $this->assertTrue($manager->hasDriver('eloquent'));
        $this->assertTrue($manager->hasDriver('database'));

        $this->assertFalse($manager->hasResolved());

        $this->assertInstanceOf(EloquentTenantProvider::class, $manager->get('tenants'));
        $this->assertInstanceOf(DatabaseTenantProvider::class, $manager->get('backup'));

        $this->assertTrue($manager->hasResolved('tenants'));
        $this->assertTrue($manager->hasResolved('backup'));

        $manager->flushResolved();

        $this->assertFalse($manager->hasResolved('tenants'));
        $this->assertFalse($manager->hasResolved('backup'));
    }

    #[Test]
    public function errorsIfTheresNoConfigCanBeFoundForADriver(): void
    {
        $manager = sprout()->providers();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The config for [provider::missing] could not be found');

        $manager->get('missing');
    }

    #[Test, DefineEnvironment('withoutDefault')]
    public function errorsIfTheresNoDefault(): void
    {
        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('There is no default provider set');

        $manager = sprout()->providers();

        $manager->get();
    }

    #[Test]
    public function errorsIfTheresNoCreatorForADriver(): void
    {
        $manager = sprout()->providers();

        config()->set('multitenancy.providers.missing', []);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The creator for [provider::missing] could not be found');

        $manager->get('missing');
    }

    #[Test]
    public function errorsIfNoEloquentModelIsProvided(): void
    {
        config()->set('multitenancy.providers.tenants.model', null);

        $manager = sprout()->providers();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The provider [tenants] is missing a required value for \'model\'');

        $manager->get('tenants');
    }

    #[Test]
    public function errorsIfTheEloquentModelConfigIsInvalid(): void
    {
        config()->set('multitenancy.providers.tenants.model', stdClass::class);

        $manager = sprout()->providers();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The provided value for \'model\' is not valid for provider [tenants]');

        $manager->get('tenants');
    }

    #[Test]
    public function errorsIfTheDatabaseEntityConfigIsInvalid(): void
    {
        config()->set('multitenancy.providers.backup.entity', stdClass::class);

        $manager = sprout()->providers();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The provided value for \'entity\' is not valid for provider [backup]');

        $manager->get('backup');
    }

    #[Test]
    public function errorsIfNoDatabaseTableIsProvided(): void
    {
        config()->set('multitenancy.providers.backup.table', null);

        $manager = sprout()->providers();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The provider [backup] is missing a required value for \'table\'');

        $manager->get('backup');
    }

    #[Test]
    public function errorsIfTheDatabaseTableIsNotAStringOrModel(): void
    {
        config()->set('multitenancy.providers.backup.table', stdClass::class);

        $manager = sprout()->providers();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The provided value for \'table\' is not valid for provider [backup]');

        $manager->get('backup');
    }

    #[Test]
    public function canUseAModelInsteadOfTableNameForDatabaseProviders(): void
    {
        config()->set('multitenancy.providers.backup.table', TenantModel::class);

        $manager = sprout()->providers();

        $this->assertSame((new TenantModel())->getTable(), $manager->get('backup')->getTable());
    }

    #[Test]
    public function allowsCustomCreators(): void
    {
        config()->set('multitenancy.providers.eloquent.driver', 'hello-there');

        TenantProviderManager::register('hello-there', static function () {
            return new EloquentTenantProvider('hello-there', NoResourcesTenantModel::class);
        });

        $manager = sprout()->providers();

        $this->assertTrue($manager->hasDriver('hello-there'));
        $this->assertFalse($manager->hasResolved('eloquent'));
        $this->assertFalse($manager->hasResolved('database'));

        $provider = $manager->get('eloquent');

        $this->assertInstanceOf(EloquentTenantProvider::class, $provider);
        $this->assertSame('hello-there', $provider->getName());
        $this->assertSame(NoResourcesTenantModel::class, $provider->getModelClass());
        $this->assertTrue($manager->hasResolved('eloquent'));
        $this->assertFalse($manager->hasResolved('database'));
    }
}
