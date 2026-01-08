<?php
declare(strict_types=1);

namespace Sprout\Core\Managers;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Core\Support\BaseFactory;
use Sprout\Core\Support\DefaultTenancy;

/**
 * Tenancy Manager
 *
 * This is a manager and factory, responsible for creating and storing
 * implementations of {@see \Sprout\Core\Contracts\Tenancy}.
 *
 * @extends \Sprout\Core\Support\BaseFactory<\Sprout\Core\Contracts\Tenancy>
 */
final class TenancyManager extends BaseFactory
{
    /**
     * @var \Sprout\Core\Managers\TenantProviderManager
     */
    private TenantProviderManager $providerManager;

    /**
     * Create a new instance
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Sprout\Core\Managers\TenantProviderManager  $providerManager
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
     * @param array<string, mixed>                                          $config
     * @param string                                                        $name
     *
     * @phpstan-param array{provider?: string|null, options?: list<string>} $config
     *
     * @return \Sprout\Core\Support\DefaultTenancy<\Sprout\Core\Contracts\Tenant>
     */
    protected function createDefaultTenancy(array $config, string $name): DefaultTenancy
    {
        return new DefaultTenancy(
            $name,
            $this->providerManager->get($config['provider'] ?? null),
            $config['options'] ?? []
        );
    }
}
