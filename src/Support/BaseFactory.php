<?php
declare(strict_types=1);

namespace Sprout\Support;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Base Factory
 *
 * This is an abstract base factory used by Sprout internals.
 *
 * @template FactoryClass of object
 *
 * @package Core
 *
 * @internal
 */
abstract class BaseFactory
{
    /**
     * Custom creators
     *
     * @var array<string, \Closure>
     *
     * @phpstan-var array<string, \Closure(Application, array<string, mixed>, string): FactoryClass>
     */
    protected static array $customCreators = [];

    /**
     * Register a custom creator
     *
     * @param string                                                                    $name
     * @param \Closure                                                                  $callback
     *
     * @phpstan-param \Closure(Application, array<string, mixed>, string): FactoryClass $callback
     *
     * @return void
     */
    public static function register(string $name, \Closure $callback): void
    {
        static::$customCreators[$name] = $callback;
    }

    /**
     * The Laravel application
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected Application $app;

    /**
     * Previously created objects
     *
     * @var array<string, object>
     *
     * @phpstan-var array<string, FactoryClass>
     */
    protected array $objects = [];

    /**
     * Create a new factory instance
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get the name used by this factory
     *
     * @return string
     */
    abstract protected function getFactoryName(): string;

    /**
     * Get the config key for the given name
     *
     * @param string $name
     *
     * @return string
     */
    abstract protected function getConfigKey(string $name): string;

    /**
     * Get the default name
     *
     * @return string
     */
    protected function getDefaultName(): string
    {
        /** @phpstan-ignore-next-line */
        return $this->app['config']->get('multitenancy.defaults.' . $this->getFactoryName());
    }

    /**
     * Get the config for the given name
     *
     * @param string $name
     *
     * @return array<string, mixed>|null
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function getConfig(string $name): ?array
    {
        /** @phpstan-ignore-next-line */
        return $this->app['config']->get($this->getConfigKey($name));
    }

    /**
     * Call a custom object creator
     *
     * @param string               $name
     * @param array<string, mixed> $config
     *
     * @return object
     *
     * @phpstan-return FactoryClass
     */
    protected function callCustomCreator(string $name, array $config): object
    {
        if (! isset(static::$customCreators[$name])) {
            throw new InvalidArgumentException(
                'Custom creator [' . $name . '] does not exist'
            );
        }

        $creator = static::$customCreators[$name];

        return $creator($this->app, $config, $name);
    }

    /**
     * Resolve an object by name
     *
     * @param string $name
     *
     * @return object
     *
     * @phpstan-return FactoryClass
     */
    protected function resolve(string $name): object
    {
        // We need config, even if it's empty
        try {
            $config = $this->getConfig($name);
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            throw new RuntimeException(
                'Unable to load config for [' . $this->getFactoryName() . '::' . $name . ']',
                previous: $e
            );
        }

        // If there's no config, complain
        if ($config === null) {
            throw new InvalidArgumentException(
                'Invalid [' . $this->getFactoryName() . '], no config found'
            );
        }

        // Ooo custom creation logic, let's use that
        if (isset($this->customCreators[$name])) {
            return $this->callCustomCreator($name, $config);
        }

        // Is there a driver?
        if (isset($config['driver'])) {
            // This has a driver, so we'll see if we can create based on that
            /** @phpstan-ignore-next-line */
            $method = 'create' . ucfirst($config['driver']) . ucfirst($this->getFactoryName());
        } else {
            // There's no driver, so we'll see if there's a default available
            $method = 'createDefault' . ucfirst($this->getFactoryName());
        }

        // Does the creator method exist?
        if (method_exists($this, $method)) {
            // It does, use it
            return $this->{$method}($config, $name);
        }

        // There's no valid creator, so we'll complain
        throw new InvalidArgumentException(
            'Unable to create [' . $this->getFactoryName() . '::' . $name . '], no valid creator found'
        );
    }

    /**
     * Get an object
     *
     * @param string|null $name
     *
     * @return object
     *
     * @phpstan-return FactoryClass
     */
    public function get(?string $name = null): object
    {
        $name ??= $this->getDefaultName();

        if (! isset($this->objects[$name])) {
            $this->objects[$name] = $this->resolve($name);
        }

        return $this->objects[$name];
    }

    /**
     * Flush all resolved objects
     *
     * @return static
     */
    public function flushResolved(): static
    {
        $this->objects = [];

        return $this;
    }
}
