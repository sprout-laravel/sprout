<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides\Auth;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class BudAuthManager extends AuthManager
{
    protected bool $syncedFromOriginal = false;

    public function __construct($app, ?AuthManager $original = null)
    {
        parent::__construct($app);

        if ($original) {
            $this->syncOriginal($original);
        }
    }

    /**
     * Check if this manager override was synced from the original
     *
     * @return bool
     */
    public function wasSyncedFromOriginal(): bool
    {
        return $this->syncedFromOriginal;
    }

    /**
     * Sync the original manager in case things have been registered
     *
     * @param \Illuminate\Auth\AuthManager $original
     *
     * @return void
     */
    private function syncOriginal(AuthManager $original): void
    {
        $this->customCreators         = array_merge($original->customCreators, $this->customCreators);
        $this->guards                 = array_merge($original->guards, $this->guards);
        $this->userResolver           = $original->userResolver;
        $this->customProviderCreators = array_merge($original->customProviderCreators, $this->customProviderCreators);
        $this->syncedFromOriginal     = true;
    }

    /**
     * Create the user provider implementation for the driver.
     *
     * @param string|null $provider
     *
     * @return \Illuminate\Contracts\Auth\UserProvider|null
     *
     * @throws \InvalidArgumentException
     */
    public function createUserProvider($provider = null): ?UserProvider
    {
        $provider ??= $this->getDefaultUserProvider();
        $config   = $this->getProviderConfiguration($provider);

        if ($config === null) {
            return null;
        }

        $config = Arr::add($config, 'provider', $provider);

        /** @var array<string, mixed>|array{provider:string,driver?:string|null} $config */

        return $this->createUserProviderFromConfig($config);
    }

    /**
     * Create the user provider implementation from the given configuration.
     *
     * @param array<string, mixed>|array{provider:string,driver?:string|null} $config
     *
     * @return \Illuminate\Contracts\Auth\UserProvider|null
     */
    public function createUserProviderFromConfig(array $config): ?UserProvider
    {
        $driver = $config['driver'] ?? null;

        if ($driver === null) {
            throw new InvalidArgumentException('Authentication user provider driver must be provided.');
        }

        /** @var string $driver */

        if (isset($this->customProviderCreators[$driver])) {
            $creator = $this->customProviderCreators[$driver];
            /** @var \Closure(\Illuminate\Contracts\Foundation\Application, array<string, mixed>):UserProvider|null $creator */

            /**
             * This has to be here because no matter how I provide it, it
             * misread the type of creator as being nullable, which it is not.
             *
             * @phpstan-ignore callable.nonCallable
             */
            return $creator($this->app, $config);
        }

        /** @var \Illuminate\Contracts\Auth\UserProvider|null */
        return match ($driver) {
            'database' => $this->createDatabaseProvider($config),
            'eloquent' => $this->createEloquentProvider($config),
            default    => throw new InvalidArgumentException(
                "Authentication user provider [{$driver}] is not defined."
            ),
        };
    }
}
