<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Concerns;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Contracts\Tenancy;
use Sprout\Http\Resolvers\PathIdentityResolver;
use Sprout\Tests\Unit\UnitTestCase;

class FindsIdentityInRouteParameterTest extends UnitTestCase
{
    #[Test]
    public function constructorParameterOverrideUpdatesTheStoredParameter(): void
    {
        // Default parameter pattern is '{tenancy}_{resolver}'.
        // Passing a custom $parameter triggers setParameter() inside
        // initialiseRouteParameter().
        $resolver = new PathIdentityResolver(
            name: 'path',
            parameter: 'custom_param',
        );

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('tenants')->atLeast()->once();
        });

        // The custom parameter has no placeholders, so the resolved name is the literal value.
        $this->assertSame('custom_param', $resolver->getRouteParameterName($tenancy));
        $this->assertSame('{custom_param}', $resolver->getRouteParameter($tenancy));
    }

    #[Test]
    public function resolveFromRouteFallsBackToResolveFromRequestWhenParameterIsAbsent(): void
    {
        $resolver = new PathIdentityResolver('path');

        $tenancy = Mockery::mock(Tenancy::class, static function (MockInterface $mock) {
            $mock->shouldReceive('getName')->andReturn('tenants')->atLeast()->once();
        });

        $route = Mockery::mock(Route::class, static function (MockInterface $mock) {
            $mock->shouldReceive('hasParameter')->with('tenants_path')->andReturn(false)->once();
        });

        $request = Mockery::mock(Request::class, static function (MockInterface $mock) {
            $mock->shouldReceive('segment')->with(1)->andReturn('fallback-identifier')->once();
        });

        $this->assertSame('fallback-identifier', $resolver->resolveFromRoute($route, $tenancy, $request));
    }
}
