<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Exceptions\CompatibilityException;
use Sprout\Http\RouteCreator;
use Sprout\Tests\Unit\UnitTestCase;

class RouteCreatorTest extends UnitTestCase
{
    protected function defineEnvironment($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.resolver', 'path');
        });
    }

    #[Test]
    public function throwsCompatibilityExceptionWhenOptionalIsUsedWithAParameterResolver(): void
    {
        $this->expectException(CompatibilityException::class);
        $this->expectExceptionMessageMatches(
            '/^Cannot use optional tenant middleware with the non-parameter based resolver \[path\]\.$/'
        );

        RouteCreator::create(static function (): void {
        }, resolver: 'path', optional: true);
    }
}
