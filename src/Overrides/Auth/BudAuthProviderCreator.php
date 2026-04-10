<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides\Auth;

use Illuminate\Contracts\Auth\UserProvider;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\BaseCreator;
use Sprout\Core\Sprout;

final class BudAuthProviderCreator extends BaseCreator
{
    private BudAuthManager $manager;

    private Bud $bud;

    private Sprout $sprout;

    private string $name;

    /**
     * @var array<string, mixed>&array{budStore?:string|null}
     */
    private array $config;

    /**
     * @param \Sprout\Bud\Overrides\Auth\BudAuthManager    $manager
     * @param \Sprout\Bud\Bud                              $bud
     * @param \Sprout\Core\Sprout                               $sprout
     * @param string                                            $name
     * @param array<string, mixed>&array{budStore?:string|null} $config
     */
    public function __construct(
        BudAuthManager $manager,
        Bud            $bud,
        Sprout         $sprout,
        string         $name,
        array          $config
    )
    {
        $this->manager = $manager;
        $this->bud     = $bud;
        $this->sprout  = $sprout;
        $this->name    = $name;
        $this->config  = $config;
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

    public function __invoke(): ?UserProvider
    {
        /** @var array<string, mixed>&array{driver:string|null} $config */
        $config = $this->getConfig($this->sprout, $this->bud, $this->config, $this->name);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['driver'] ?? null,
            'auth provider',
            $this->name
        );

        return $this->manager->createUserProviderFromConfig($config);
    }
}
