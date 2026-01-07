<?php
declare(strict_types=1);

namespace Sprout\Bud\Stores;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\MisconfigurationException;

/**
 * Filesystem Config Store
 *
 * This class is responsible for storing and retrieving tenant-specific
 * configuration values using a filesystem disk.
 */
final class FilesystemConfigStore extends BaseConfigStore
{
    /**
     * @var \Illuminate\Contracts\Filesystem\Filesystem
     */
    private Filesystem $filesystem;

    public function __construct(
        string     $name,
        Encrypter  $encrypter,
        Filesystem $filesystem
    )
    {
        parent::__construct($name, $encrypter);

        $this->filesystem = $filesystem;
    }

    /**
     * Get the path to the config file
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     * @param string                                 $service
     * @param string                                 $name
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return string
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    protected function getPath(Tenancy $tenancy, Tenant $tenant, string $service, string $name): string
    {
        if (! ($tenant instanceof TenantHasResources)) {
            throw MisconfigurationException::misconfigured('tenant', (string)$tenant->getTenantKey(), 'resources');
        }

        $resourceKey = $tenant->getTenantResourceKey();

        return $tenancy->getName()
               . DIRECTORY_SEPARATOR
               . Str::substr($resourceKey, 0, 2)
               . DIRECTORY_SEPARATOR
               . Str::substr($resourceKey, 2)
               . DIRECTORY_SEPARATOR
               . Str::slug($service)
               . DIRECTORY_SEPARATOR
               . Str::slug($name);
    }

    /**
     * Get a config value from the store
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     * @param string                                 $service
     * @param string                                 $name
     * @param array<string, mixed>|null              $default
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return array<string, mixed>|null
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function get(Tenancy $tenancy, Tenant $tenant, string $service, string $name, ?array $default = null): ?array
    {
        $value = $this->filesystem->get($this->getPath($tenancy, $tenant, $service, $name));

        if ($value === null) {
            return $default;
        }

        return $this->decryptConfig($value) ?? $default;
    }

    /**
     * Check if the config store has a value
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     * @param string                                 $service
     * @param string                                 $name
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return bool
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function has(Tenancy $tenancy, Tenant $tenant, string $service, string $name): bool
    {
        return $this->filesystem->exists($this->getPath($tenancy, $tenant, $service, $name));
    }

    /**
     * Set a config value in the store
     *
     * Setting a config value ensures that the config is present within the
     * store for the given tenant, either by adding the entry if there wasn't
     * one, or overwriting one if it already existed.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     * @param string                                 $service
     * @param string                                 $name
     * @param array<string, mixed>                   $config
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return bool
     *
     * @throws \JsonException
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function set(Tenancy $tenancy, Tenant $tenant, string $service, string $name, array $config): bool
    {
        return $this->filesystem->put(
            $this->getPath($tenancy, $tenant, $service, $name),
            $this->encryptConfig($config)
        );
    }

    /**
     * Add a config value to the store
     *
     * Adding a config value will create a new entry within the store for the
     * given tenant if one doesn't already exist. If an entry already exists,
     * this method will return false.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     * @param string                                 $service
     * @param string                                 $name
     * @param array<string, mixed>                   $config
     *
     * @return bool
     *
     * @throws \JsonException
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function add(Tenancy $tenancy, Tenant $tenant, string $service, string $name, array $config): bool
    {
        if ($this->has($tenancy, $tenant, $service, $name)) {
            return false;
        }

        return $this->set($tenancy, $tenant, $service, $name, $config);
    }
}
