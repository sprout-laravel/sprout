<?php
declare(strict_types=1);

namespace Sprout\Core\Overrides\Auth;

use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Contracts\Foundation\Application;
use Sprout\Core\Sprout;

/**
 * Sprout Auth Password Broker Manager
 *
 * This is an override of the default password broker manager to make it
 * create a tenant-aware {@see \Illuminate\Auth\Passwords\TokenRepositoryInterface}.
 *
 * This is an unfortunate necessity as there's no other way to control the
 * token repository that is created.
 *
 * @package Overrides
 */
class SproutAuthPasswordBrokerManager extends PasswordBrokerManager
{
    /**
     * @var \Sprout\Core\Sprout
     */
    private Sprout $sprout;

    public function __construct(Application $app, Sprout $sprout)
    {
        parent::__construct($app);

        $this->sprout = $sprout;
    }

    /**
     * Create a token repository instance based on the current configuration.
     *
     * @param array<string, mixed> $config
     *
     * @return \Illuminate\Auth\Passwords\TokenRepositoryInterface
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function createTokenRepository(array $config): TokenRepositoryInterface
    {
        /** @var string $key */
        $key = $this->app->make('config')->get('app.key');

        // @codeCoverageIgnoreStart
        if (str_starts_with($key, 'base64:')) {    // @infection-ignore-all
            $key = base64_decode(substr($key, 7)); // @infection-ignore-all
        }
        // @codeCoverageIgnoreEnd
        $expiry = $config['expire'] !== 0 && is_numeric($config['expire']) ? (int)$config['expire'] : 60;

        if (isset($config['driver']) && $config['driver'] === 'cache') {

            return new SproutAuthCacheTokenRepository(
                $this->sprout,
                $this->app->make('cache')->store($config['store'] ?? null), // @phpstan-ignore-line
                $this->app->make('hash'),
                $key,
                $expiry * 60,
                $config['throttle'] ?? 0, // @phpstan-ignore-line
                $config['prefix'] ?? '', // @phpstan-ignore-line
            );
        }

        $connection = $config['connection'] ?? null;

        return new SproutAuthDatabaseTokenRepository(
            $this->sprout,
            $this->app->make('db')->connection($connection), // @phpstan-ignore-line
            $this->app->make('hash'),
            $config['table'], // @phpstan-ignore-line
            $key,
            $expiry * 60,
            $config['throttle'] ?? 0// @phpstan-ignore-line
        );
    }

    /**
     * Check if a broker has been resolved
     *
     * @param string|null $name
     *
     * @return bool
     */
    public function isResolved(?string $name = null): bool
    {
        return isset($this->brokers[$name ?? $this->getDefaultDriver()]);
    }

    /**
     * Flush the resolved brokers
     *
     * @return $this
     */
    public function flush(): self
    {
        $this->brokers = [];

        return $this;
    }
}
