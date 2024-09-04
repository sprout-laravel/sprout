<?php
declare(strict_types=1);

namespace Sprout\Managers;

use Illuminate\Contracts\Foundation\Application;
use Sprout\Support\BaseFactory;
use Sprout\Support\DefaultTenancy;

/**
 * @extends \Sprout\Support\BaseFactory<\Sprout\Contracts\Tenancy>
 */
final class TenancyManager extends BaseFactory
{
    /**
     * @var \Sprout\Managers\ProviderManager
     */
    private ProviderManager $providerManager;

    public function __construct(Application $app, ProviderManager $providerManager)
    {
        parent::__construct($app);

        $this->providerManager = $providerManager;
    }

    /**
     * Get the name used by this factory
     *
     * @return string
     */
    protected function getFactoryName(): string
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
    protected function getConfigKey(string $name): string
    {
        return 'multitenancy.tenancies.' . $name;
    }

    /**
     * @param array<string, mixed>                                                  $config
     * @param string                                                                $name
     *
     * @phpstan-param array{provider?: string|null, options?: array<string, mixed>} $config
     *
     * @return \Sprout\Support\DefaultTenancy<\Sprout\Contracts\Tenant>
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
