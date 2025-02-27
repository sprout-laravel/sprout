<?php
declare(strict_types=1);

namespace Sprout\Support;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Sprout;

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
     * @phpstan-var array<class-string<\Sprout\Support\BaseFactory<FactoryClass>>, array<string, \Closure(Application, array<string, mixed>, string): FactoryClass>>
     */
    protected static array $customCreators = [];

    /**
     * Register a custom creator
     *
     * @param string                                                                    $name
     * @param \Closure                                                                  $creator
     *
     * @phpstan-param \Closure(Application, array<string, mixed>, string): FactoryClass $creator
     *
     * @return void
     */
    public static function register(string $name, Closure $creator): void
    {
        static::$customCreators[static::class][$name] = $creator;
    }

    /**
     * The Laravel application
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected Application $app;

    /**
     * The Laravel config
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    private Repository $config;

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
     * Check if a factory has a driver
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasDriver(string $name): bool
    {
        return isset(static::$customCreators[static::class][$name])
               || method_exists($this, 'create' . ucfirst($name) . ucfirst($this->getFactoryName()));
    }

    /**
     * Get the name used by this factory
     *
     * @return string
     */
    abstract public function getFactoryName(): string;

    /**
     * Get the config key for the given name
     *
     * @param string $name
     *
     * @return string
     */
    abstract public function getConfigKey(string $name): string;

    /**
     * Get the default name
     *
     * @return string
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function getDefaultName(): string
    {
        /** @var string|null $name */
        $name = $this->getAppConfig()->get('multitenancy.defaults.' . $this->getFactoryName());

        if ($name === null) {
            throw MisconfigurationException::noDefault($this->getFactoryName());
        }

        return $name;
    }

    /**
     * Get the config for the given name
     *
     * @param string $name
     *
     * @return array<string, mixed>|null
     */
    protected function getConfig(string $name): ?array
    {
        /** @var array<string,mixed>|null $config */
        $config = $this->getAppConfig()->get($this->getConfigKey($name));

        return $config;
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
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    protected function callCustomCreator(string $name, array $config): object
    {
        if (! isset(static::$customCreators[static::class][$name])) {
            // @codeCoverageIgnoreStart
            throw MisconfigurationException::notFound(
                'custom creator',
                $this->getFactoryName() . '::' . $name
            );
            // @codeCoverageIgnoreEnd
        }

        $creator = static::$customCreators[static::class][$name];

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
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function resolve(string $name): object
    {
        // We need config, even if it's empty
        $config = $this->getConfig($name);

        // If there's no config, complain
        if ($config === null) {
            throw MisconfigurationException::notFound('config', $this->getFactoryName() . '::' . $name);
        }

        /** @var string|null $driver */
        $driver = $config['driver'] ?? null;

        // Is there a driver?
        if ($driver !== null) {
            // Is there a custom creator for the driver?
            if (isset(static::$customCreators[static::class][$driver])) {
                return $this->setupResolvedObject($this->callCustomCreator($driver, $config));
            }

            // This has a driver, so we'll see if we can create based on that
            $method = 'create' . ucfirst($driver) . ucfirst($this->getFactoryName());
        } else {
            // There's no driver, so we'll see if there's a default available
            $method = 'createDefault' . ucfirst($this->getFactoryName());
        }

        // Does the creator method exist?
        if (method_exists($this, $method)) {
            // It does, use it
            return $this->setupResolvedObject($this->{$method}($config, $name));
        }

        // There's no valid creator, so we'll complain
        throw MisconfigurationException::notFound('creator', $this->getFactoryName() . '::' . $name);
    }

    /**
     * Get an object
     *
     * @param string|null $name
     *
     * @return object
     *
     * @phpstan-return FactoryClass
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
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

    /**
     * Check if a driver has already been resolved
     *
     * @param string|null $name
     *
     * @return bool
     */
    public function hasResolved(?string $name = null): bool
    {
        if ($name === null) {
            return ! empty($this->objects);
        }

        return isset($this->objects[$name]);
    }

    /**
     * Set up an object resolved by the factory
     *
     * @param object               $object
     *
     * @return object
     *
     * @phpstan-param FactoryClass $object
     * @phpstan-return FactoryClass
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function setupResolvedObject(object $object): object
    {
        if (method_exists($object, 'setApp')) {
            $object->setApp($this->app);
        }

        if (method_exists($object, 'setSprout')) {
            $object->setSprout($this->app->make(Sprout::class));
        }

        return $object;
    }

    /**
     * Get the application config
     *
     * @return \Illuminate\Contracts\Config\Repository
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getAppConfig(): Repository
    {
        if (! isset($this->config)) {
            $this->config = $this->app->make('config');
        }

        return $this->config;
    }
}
