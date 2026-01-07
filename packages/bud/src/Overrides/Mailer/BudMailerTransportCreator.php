<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides\Mailer;

use Illuminate\Mail\MailManager;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\BaseCreator;
use Sprout\Sprout;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Bud Mailer Transport Creator
 *
 * This class is an abstraction for the logic that creates a mailer
 * using a config store within Bud.
 */
final class BudMailerTransportCreator extends BaseCreator
{
    private MailManager $manager;

    private Bud $bud;

    private Sprout $sprout;

    private string $name;

    /**
     * @var array<string, mixed>&array{budStore?:string|null}
     */
    private array $config;

    /**
     * @param \Illuminate\Mail\MailManager                      $manager
     * @param \Sprout\Bud\Bud                                   $bud
     * @param \Sprout\Sprout                                    $sprout
     * @param string                                            $name
     * @param array<string, mixed>&array{budStore?:string|null} $config
     */
    public function __construct(
        MailManager $manager,
        Bud         $bud,
        Sprout      $sprout,
        string      $name,
        array       $config = []
    )
    {
        $this->manager = $manager;
        $this->bud     = $bud;
        $this->sprout  = $sprout;
        $this->name    = $name;
        $this->config  = $config;
    }

    /**
     * @return \Symfony\Component\Mailer\Transport\TransportInterface
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function __invoke(): TransportInterface
    {
        /** @var array<string, mixed>&array{transport?:string|null} $config */
        $config = $this->getConfig($this->sprout, $this->bud, $this->config, $this->name);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['transport'] ?? null,
            'mailer',
            $this->name
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
