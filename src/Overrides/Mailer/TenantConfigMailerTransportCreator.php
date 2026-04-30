<?php
declare(strict_types=1);

namespace Sprout\Overrides\Mailer;

use Illuminate\Mail\MailManager;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Overrides\BaseCreator;
use Sprout\Sprout;
use Sprout\TenantConfig;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Tenant Config Mailer Transport Creator
 *
 * This class is an abstraction for the logic that creates a mailer
 * using a config store within tenant config.
 */
final class TenantConfigMailerTransportCreator extends BaseCreator
{
    private MailManager $manager;

    private TenantConfig $tenantConfig;

    private Sprout $sprout;

    private string $name;

    /**
     * @var array<string, mixed>&array{configStore?:string|null}
     */
    private array $config;

    /**
     * @param MailManager                                          $manager
     * @param TenantConfig                                         $tenantConfig
     * @param Sprout                                               $sprout
     * @param string                                               $name
     * @param array<string, mixed>&array{configStore?:string|null} $config
     */
    public function __construct(
        MailManager  $manager,
        TenantConfig $tenantConfig,
        Sprout       $sprout,
        string       $name,
        array        $config = [],
    ) {
        $this->manager      = $manager;
        $this->tenantConfig = $tenantConfig;
        $this->sprout       = $sprout;
        $this->name         = $name;
        $this->config       = $config;
    }

    /**
     * @return TransportInterface
     *
     * @throws MisconfigurationException
     * @throws TenancyMissingException
     * @throws TenantMissingException
     */
    public function __invoke(): TransportInterface
    {
        /** @var array<string, mixed>&array{transport?:string|null} $config */
        $config = $this->getConfig($this->sprout, $this->tenantConfig, $this->config, $this->name);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['transport'] ?? null,
            'mailer',
            $this->name,
        );

        return $this->manager->createSymfonyTransport($config);
    }

    /**
     * Get the name of the service for the creator.
     *
     * @return string
     */
    protected function getService(): string
    {
        return 'mailer';
    }
}
