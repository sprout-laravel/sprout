<?php
declare(strict_types=1);

namespace Sprout\Overrides\Auth;

use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use RuntimeException;
use Sprout\Sprout;

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
     * @var \Sprout\Sprout
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
     * @phpstan-param array{
     *     driver?: string|null,
     *     provider?: string|null,
     *     connection?: string|null,
     *     table?: string,
     *     store?: string|null,
     *     prefix?: string,
     *     expire?: int|null,
     *     throttle?: int|null,
     * } $config
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

        if (isset($config['driver']) && $config['driver'] === 'cache') {
            $cache = $this->app->make('cache')->store($config['store'] ?? null);

            if (! ($cache instanceof CacheRepository)) {
                throw new RuntimeException('Expected an instance of ' . CacheRepository::class);
            }

            return new SproutAuthCacheTokenRepository(
                $this->sprout,
                $cache,
                $this->app->make('hash'),
                $key,
                ($config['expire'] ?? 60) * 60,
                $config['throttle'] ?? 0,
                $config['prefix'] ?? '',
            );
        }

        return new SproutAuthDatabaseTokenRepository(
            $this->sprout,
            $this->app->make('db')->connection($config['connection'] ?? null),
            $this->app->make('hash'),
            $config['table'] ?? '',
            $key,
            $this->laravelVersionedExpiry($config['expire'] ?? null),
            $config['throttle'] ?? 0
        );
    }

    /**
     * Create the token expiry based on the Laravel version
     *
     * @param int|null $expiry
     *
     * @return int|null
     */
    private function laravelVersionedExpiry(?int $expiry): ?int
    {
        if ($expiry === null) {
            return null;
        }

        if (! Str::startsWith($this->app->version(), '11.')) {
            return $expiry * 60;
        }

        return $expiry;
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
