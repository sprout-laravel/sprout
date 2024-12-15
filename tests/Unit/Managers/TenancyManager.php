<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Managers;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Tests\Unit\UnitTestCase;
use function Sprout\sprout;

class TenancyManager extends UnitTestCase
{
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
}
