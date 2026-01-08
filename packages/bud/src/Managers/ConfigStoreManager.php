<?php
declare(strict_types=1);

namespace Sprout\Bud\Managers;

use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use Sprout\Bud\Stores\DatabaseConfigStore;
use Sprout\Bud\Stores\FilesystemConfigStore;
use Sprout\Core\Exceptions\MisconfigurationException;
use Sprout\Core\Support\BaseFactory;

/**
 * @extends \Sprout\Core\Support\BaseFactory<\Sprout\Bud\Contracts\ConfigStore>
 */
class ConfigStoreManager extends BaseFactory
{
    /**
     * Get the name used by this factory
     *
     * @return string
     */
    public function getFactoryName(): string
    {
        return 'config';
    }

    /**
     * Get the config key for the given name
     *
     * @param string $name
     *
     * @return string
     */
    public function getConfigKey(string $name): string
    {
        return 'sprout.bud.stores.' . $name;
    }

    /**
     * Get the encrypter
     *
     * If a key is provided, a new encrypter will be created using the provided
     * key with a custom cipher if one is provided.
     *
     * @param string|null $key
     * @param string|null $cipher
     *
     * @return \Illuminate\Contracts\Encryption\Encrypter
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getEncrypter(?string $key, ?string $cipher): EncrypterContract
    {
        /** @var \Illuminate\Contracts\Encryption\Encrypter $encrypter */
        $encrypter = $key ? $this->buildEncrypter($key, $cipher) : $this->app->make('encrypter');

        return $encrypter;
    }

    /**
     * Build a custom encrypter
     *
     * @param string      $key
     * @param string|null $cipher
     *
     * @return \Illuminate\Contracts\Encryption\Encrypter
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function buildEncrypter(string $key, ?string $cipher = null): EncrypterContract
    {
        $cipher ??= $this->app->make('config')->get('app.cipher', 'AES-256-CBC');

        /** @var string $cipher */

        // If the key is base64 encoded, we'll decode it before passing it to the encrypter
        if (Str::startsWith($key, $prefix = 'base64:')) {
            $key = base64_decode(Str::after($key, $prefix));
        }

        return new Encrypter($key, $cipher);
    }

    /**
     * Create a config store for the filesystem driver
     *
     * @param array<string, mixed> $config
     * @param string               $name
     *
     * @return \Sprout\Bud\Stores\FilesystemConfigStore
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     */
    protected function createFilesystemConfig(array $config, string $name): FilesystemConfigStore
    {
        /** @var array{disk?:string,directory?:string,key?:string,cipher?:string|null} $config */

        if (! isset($config['disk'])) {
            throw MisconfigurationException::missingConfig('disk', 'config store', $name);
        }

        if (isset($config['directory'])) {
            // If there's a subdirectory, we'll create a scoped driver, for simplicity
            $disk = $this->app->make('filesystem')->createScopedDriver([
                'disk'   => $config['disk'],
                'prefix' => $config['directory'],
            ]);
        } else {
            // Otherwise we'll just use the disk as is
            $disk = $this->app->make('filesystem')->disk($config['disk']);
        }

        return new FilesystemConfigStore(
            $name,
            $this->getEncrypter($config['key'] ?? null, $config['cipher'] ?? null),
            $disk
        );
    }

    /**
     * Create a config store for the database driver
     *
     * @param array<string, mixed> $config
     * @param string               $name
     *
     * @return \Sprout\Bud\Stores\DatabaseConfigStore
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     */
    protected function createDatabaseConfig(array $config, string $name): DatabaseConfigStore
    {
        /** @var array{connection?:string|null,table?:string,key?:string,cipher?:string|null} $config */

        if (! isset($config['table'])) {
            throw MisconfigurationException::missingConfig('table', 'config store', $name);
        }

        return new DatabaseConfigStore(
            $name,
            $this->getEncrypter($config['key'] ?? null, $config['cipher'] ?? null),
            $this->app->make('db')->connection($config['connection'] ?? null),
            $config['table']
        );
    }
}
