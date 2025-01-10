<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Managers;

use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Http\Resolvers\CookieIdentityResolver;
use Sprout\Http\Resolvers\HeaderIdentityResolver;
use Sprout\Http\Resolvers\PathIdentityResolver;
use Sprout\Http\Resolvers\SessionIdentityResolver;
use Sprout\Http\Resolvers\SubdomainIdentityResolver;
use Sprout\Managers\IdentityResolverManager;
use Sprout\Tests\Unit\UnitTestCase;
use function Sprout\resolver;
use function Sprout\sprout;

class IdentityResolverManagerTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.subdomain.domain', 'localhost');
        });
    }

    protected function withoutDefault($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.resolver', null);
        });
    }

    protected function withoutConfig($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.resolvers.path', null);
        });
    }

    #[Test]
    public function isNamedCorrectly(): void
    {
        $manager = sprout()->resolvers();

        $this->assertSame('resolver', $manager->getFactoryName());
    }

    #[Test]
    public function getsTheDefaultNameFromTheConfig(): void
    {
        $manager = sprout()->resolvers();

        $this->assertSame('subdomain', $manager->getDefaultName());

        config()->set('multitenancy.defaults.resolver', 'path');

        $this->assertSame('path', $manager->getDefaultName());
    }

    #[Test]
    public function generatesConfigKeys(): void
    {
        $manager = sprout()->resolvers();

        $this->assertSame('multitenancy.resolvers.test-config', $manager->getConfigKey('test-config'));
    }

    #[Test]
    public function hasDefaultFirstPartyDrivers(): void
    {
        $manager = sprout()->resolvers();

        $this->assertFalse($manager->hasResolved());

        $this->assertTrue($manager->hasDriver('subdomain'));
        $this->assertTrue($manager->hasDriver('path'));
        $this->assertTrue($manager->hasDriver('header'));
        $this->assertTrue($manager->hasDriver('cookie'));
        $this->assertTrue($manager->hasDriver('session'));
        $this->assertFalse($manager->hasDriver('fake-driver'));

        $this->assertFalse($manager->hasResolved());

        $this->assertInstanceOf(SubdomainIdentityResolver::class, $manager->get('subdomain'));
        $this->assertInstanceOf(PathIdentityResolver::class, $manager->get('path'));
        $this->assertInstanceOf(HeaderIdentityResolver::class, $manager->get('header'));
        $this->assertInstanceOf(CookieIdentityResolver::class, $manager->get('cookie'));
        $this->assertInstanceOf(SessionIdentityResolver::class, $manager->get('session'));

        $this->assertTrue($manager->hasResolved('subdomain'));
        $this->assertTrue($manager->hasResolved('path'));
        $this->assertTrue($manager->hasResolved('header'));
        $this->assertTrue($manager->hasResolved('cookie'));
        $this->assertTrue($manager->hasResolved('session'));
    }

    #[Test]
    public function canFlushResolvedInstances(): void
    {
        $manager = sprout()->resolvers();

        $this->assertFalse($manager->hasResolved());

        $this->assertTrue($manager->hasDriver('subdomain'));
        $this->assertTrue($manager->hasDriver('path'));
        $this->assertTrue($manager->hasDriver('header'));
        $this->assertTrue($manager->hasDriver('cookie'));
        $this->assertTrue($manager->hasDriver('session'));

        $this->assertFalse($manager->hasResolved());

        $this->assertInstanceOf(SubdomainIdentityResolver::class, $manager->get('subdomain'));
        $this->assertInstanceOf(PathIdentityResolver::class, $manager->get('path'));
        $this->assertInstanceOf(HeaderIdentityResolver::class, $manager->get('header'));
        $this->assertInstanceOf(CookieIdentityResolver::class, $manager->get('cookie'));
        $this->assertInstanceOf(SessionIdentityResolver::class, $manager->get('session'));

        $this->assertTrue($manager->hasResolved('subdomain'));
        $this->assertTrue($manager->hasResolved('path'));
        $this->assertTrue($manager->hasResolved('header'));
        $this->assertTrue($manager->hasResolved('cookie'));
        $this->assertTrue($manager->hasResolved('session'));

        $manager->flushResolved();

        $this->assertFalse($manager->hasResolved('subdomain'));
        $this->assertFalse($manager->hasResolved('path'));
        $this->assertFalse($manager->hasResolved('header'));
        $this->assertFalse($manager->hasResolved('cookie'));
        $this->assertFalse($manager->hasResolved('session'));
    }

    #[Test]
    public function errorsIfTheresNoConfigCanBeFoundForADriver(): void
    {
        $manager = sprout()->resolvers();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The config for [resolver::missing] could not be found');

        $manager->get('missing');
    }

    #[Test]
    public function errorsIfTheresNoCreatorForADriver(): void
    {
        $manager = sprout()->resolvers();

        config()->set('multitenancy.resolvers.missing', []);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The creator for [resolver::missing] could not be found');

        $manager->get('missing');
    }

    #[Test]
    public function errorsIfNoSubdomainDomainWasProvided(): void
    {
        config()->set('multitenancy.resolvers.subdomain.domain', null);

        $manager = sprout()->resolvers();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The resolver [subdomain] is missing a required value for \'domain\'');

        $manager->get('subdomain');
    }

    #[Test]
    public function errorsIfPathSegmentIsInvalid(): void
    {
        config()->set('multitenancy.resolvers.path.segment', -7);

        $manager = sprout()->resolvers();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The provided value for \'segment\' is not valid for resolver [path]');

        $manager->get('path');
    }

    #[Test, DefineEnvironment('withoutDefault')]
    public function errorsIfTheresNoDefault(): void
    {
        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('There is no default resolver set');

        $manager = sprout()->resolvers();

        $manager->get();
    }

    #[Test]
    public function allowsCustomCreators(): void
    {
        config()->set('multitenancy.resolvers.path.driver', 'hello-there');

        IdentityResolverManager::register('hello-there', static function () {
            return new SubdomainIdentityResolver('hello-there', 'somedomain.local');
        });

        $manager = sprout()->resolvers();

        $this->assertTrue($manager->hasDriver('hello-there'));
        $this->assertFalse($manager->hasResolved('path'));
        $this->assertFalse($manager->hasResolved('subdomain'));

        $resolver = $manager->get('path');

        $this->assertInstanceOf(SubdomainIdentityResolver::class, $resolver);
        $this->assertSame('hello-there', $resolver->getName());
        $this->assertSame('somedomain.local', $resolver->getDomain());
        $this->assertTrue($manager->hasResolved('path'));
        $this->assertFalse($manager->hasResolved('subdomain'));
    }
}
