<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Overrides;

use Illuminate\Contracts\Foundation\Application;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Overrides\AuthGuardOverride;
use Sprout\Overrides\AuthPasswordOverride;
use Sprout\Overrides\StackedOverride;
use Sprout\Sprout;
use Sprout\Support\SettingsRepository;
use Sprout\Tests\Unit\UnitTestCase;
use stdClass;

class StackedOverrideTest extends UnitTestCase
{
    #[Test]
    public function errorsWithoutOverrides(): void
    {
        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The stacked service [test] is missing a required value for \'overrides\'');

        new StackedOverride('test', []);
    }

    #[Test]
    public function errorsIfProvidedOverrideIsNotASubclassOfTheCorrectClass(): void
    {
        $app = \Mockery::mock(Application::class);

        $sprout = new Sprout($app, new SettingsRepository());

        $override = new StackedOverride('test', [
            'overrides' => [stdClass::class],
        ]);

        $override->setApp($app)->setSprout($sprout);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The provided value for \'overrides.*.driver\' [stdClass] is not valid for service override [test]');

        $override->boot($app, $sprout);
    }

    #[Test]
    public function errorsIfProvidedOverrideConfigIsMissingTheDriver(): void
    {
        $app = \Mockery::mock(Application::class);

        $sprout = new Sprout($app, new SettingsRepository());

        $override = new StackedOverride('test', [
            'overrides' => [
                [],
            ],
        ]);

        $override->setApp($app)->setSprout($sprout);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The service override [test] is missing a required value for \'overrides.*.driver\'');

        $override->boot($app, $sprout);
    }

    #[Test]
    public function errorsIfProvidedOverrideConfigIsHasAnInvalidDriver(): void
    {
        $app = \Mockery::mock(Application::class);

        $sprout = new Sprout($app, new SettingsRepository());

        $override = new StackedOverride('test', [
            'overrides' => [
                [
                    'driver' => stdClass::class,
                ],
            ],
        ]);

        $override->setApp($app)->setSprout($sprout);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The provided value for \'overrides.*.driver\' [stdClass] is not valid for service override [test]');

        $override->boot($app, $sprout);
    }

    #[Test]
    public function errorsIfProvidedOverrideIsNotStringOrArray(): void
    {
        $app = \Mockery::mock(Application::class);

        $sprout = new Sprout($app, new SettingsRepository());

        $override = new StackedOverride('test', [
            'overrides' => [
                false,
            ],
        ]);

        $override->setApp($app)->setSprout($sprout);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The provided value for \'overrides\' is not valid for service override [test]');

        $override->boot($app, $sprout);
    }

    #[Test]
    public function acceptsFullInstancesForOverrides(): void
    {
        $app = \Mockery::mock(Application::class, static function (MockInterface $mock) {
            $mock->shouldNotReceive('make');
            $mock->shouldIgnoreMissing();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $override = new StackedOverride('test', [
            'overrides' => [
                new AuthPasswordOverride('test', []),
            ],
        ]);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);
    }

    #[Test]
    public function willCreateSubOverridesUsingTheContainer(): void
    {
        $app = \Mockery::mock(Application::class, static function (MockInterface $mock) {
            $mock->shouldReceive('make')
                 ->with(
                     AuthPasswordOverride::class,
                     [
                         'service' => 'test',
                         'config'  => [],
                     ]
                 )
                 ->atLeast()
                 ->once()
                 ->andReturns(new AuthPasswordOverride('test', []));

            $mock->shouldIgnoreMissing();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $override = new StackedOverride('test', [
            'overrides' => [
                ['driver' => AuthPasswordOverride::class],
            ],
        ]);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);
    }

    #[Test]
    public function passesConfigToSubOverrides(): void
    {
        $app = \Mockery::mock(Application::class, static function (MockInterface $mock) {
            $mock->shouldReceive('make')
                 ->with(
                     AuthPasswordOverride::class,
                     [
                         'service' => 'test',
                         'config'  => ['test1' => 'value1'],
                     ]
                 )
                 ->atLeast()
                 ->once()
                 ->andReturns(new AuthPasswordOverride('test', ['test1' => 'value1']));

            $mock->shouldReceive('make')
                 ->with(
                     AuthGuardOverride::class,
                     [
                         'service' => 'test',
                         'config'  => ['test2' => 'value2'],
                     ]
                 )
                 ->atLeast()
                 ->once()
                 ->andReturns(new AuthGuardOverride('test', ['test2' => 'value2']));
            $mock->shouldIgnoreMissing();
        });

        $sprout = new Sprout($app, new SettingsRepository());

        $override = new StackedOverride('test', [
            'overrides' => [
                [
                    'driver' => AuthPasswordOverride::class,
                    'test1'  => 'value1',
                ],
                [
                    'driver' => AuthGuardOverride::class,
                    'test2'  => 'value2',
                ],
            ],
        ]);

        $override->setApp($app)->setSprout($sprout);

        $override->boot($app, $sprout);

        $overrides = $override->getOverrides();

        $this->assertCount(2, $overrides);
        $this->assertInstanceOf(AuthPasswordOverride::class, $overrides[AuthPasswordOverride::class]);
        $this->assertInstanceOf(AuthGuardOverride::class, $overrides[AuthGuardOverride::class]);
        $this->assertSame(['test1' => 'value1'], $overrides[AuthPasswordOverride::class]->getConfig());
        $this->assertSame(['test2' => 'value2'], $overrides[AuthGuardOverride::class]->getConfig());
    }
}
