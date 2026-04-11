<?php
declare(strict_types=1);

namespace Sprout\Managers;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Support\BaseFactory;
use Sprout\Support\DefaultTenancy;

/**
 * Tenancy Manager
 *
 * This is a manager and factory, responsible for creating and storing
 * implementations of {@see Tenancy}.
 *
 * @extends BaseFactory<Tenancy>
 */
final class TenancyManager extends BaseFactory
{
    /**
     * @var TenantProviderManager
     */
    private TenantProviderManager $providerManager;

    /**
     * Create a new instance
     *
     * @param Application           $app
     * @param TenantProviderManager $providerManager
     */
    public function __construct(Application $app, TenantProviderManager $providerManager)
    {
        parent::__construct($app);

        $this->providerManager = $providerManager;
    }

    /**
     * Get the name used by this factory
     *
     * @return string
     */
    public function getFactoryName(): string
    {
        return 'tenancy';
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
        return 'multitenancy.tenancies.' . $name;
    }

    /**
     * Create the default implementation
     *
     * @param array<string, mixed> $config
     * @param string               $name
     *
     * @phpstan-param array{provider?: string|null, options?: list<string>} $config
     *
     * @return DefaultTenancy<Tenant>
     */
    protected function createDefaultTenancy(array $config, string $name): DefaultTenancy
    {
        return new DefaultTenancy(
            $name,
            $this->providerManager->get($config['provider'] ?? null),
            $config['options'] ?? [],
        );
    }
}
