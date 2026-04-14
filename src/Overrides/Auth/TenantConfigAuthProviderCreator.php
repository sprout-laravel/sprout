<?php
declare(strict_types=1);

namespace Sprout\Overrides\Auth;

use Illuminate\Contracts\Auth\UserProvider;
use Sprout\TenantConfig;
use Sprout\Overrides\BaseCreator;
use Sprout\Sprout;

final class TenantConfigAuthProviderCreator extends BaseCreator
{
    private TenantConfigAuthManager $manager;

    private TenantConfig $tenantConfig;

    private Sprout $sprout;

    private string $name;

    /**
     * @var array<string, mixed>&array{configStore?:string|null}
     */
    private array $config;

    /**
     * @param TenantConfigAuthManager                                $manager
     * @param TenantConfig                                           $tenantConfig
     * @param Sprout                                                 $sprout
     * @param string                                                 $name
     * @param array<string, mixed>&array{configStore?:string|null}   $config
     */
    public function __construct(
        TenantConfigAuthManager $manager,
        TenantConfig            $tenantConfig,
        Sprout                  $sprout,
        string                  $name,
        array                   $config,
    ) {
        $this->manager      = $manager;
        $this->tenantConfig = $tenantConfig;
        $this->sprout       = $sprout;
        $this->name         = $name;
        $this->config       = $config;
    }

    public function __invoke(): ?UserProvider
    {
        /** @var array<string, mixed>&array{driver:string|null} $config */
        $config = $this->getConfig($this->sprout, $this->tenantConfig, $this->config, $this->name);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['driver'] ?? null,
            'auth provider',
            $this->name,
        );

        return $this->manager->createUserProviderFromConfig($config);
    }

    /**
     * Get the name of the service for the creator.
     *
     * @return string
     */
    protected function getService(): string
    {
        return 'auth';
    }
}
