<?php
declare(strict_types=1);

namespace Sprout\Concerns;

use Illuminate\Contracts\Foundation\Application;

/**
 * @template FactoryClass of object
 */
trait HasCustomCreators
{
    /**
     * Custom creators
     *
     * @var array<string, \Closure>
     *
     * @psalm-var array<string, \Closure(Application, array<string, mixed>, string): FactoryClass>
     * @phpstan-var array<string, \Closure(Application, array<string, mixed>, string): FactoryClass>
     */
    protected static array $customCreators = [];

    /**
     * @param string                                                                    $name
     * @param \Closure                                                                  $callback
     *
     * @psalm-param \Closure(Application, array<string, mixed>, string): FactoryClass   $callback
     * @phpstan-param \Closure(Application, array<string, mixed>, string): FactoryClass $callback
     *
     * @return void
     */
    public static function register(string $name, \Closure $callback): void
    {
        static::$customCreators[$name] = $callback;
    }
}
