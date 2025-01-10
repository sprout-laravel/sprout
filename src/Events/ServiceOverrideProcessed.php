<?php
declare(strict_types=1);

namespace Sprout\Events;

/**
 * Service Override Processed Event
 *
 * This event is dispatched after a service override has been processed.
 *
 * @template ServiceOverrideClass of \Sprout\Contracts\ServiceOverride
 *
 * @package Overrides
 *
 * @method static self dispatch(string $service, object $override)
 * @method static self dispatchIf(bool $boolean, string $service, object $override)
 * @method static self dispatchUnless(bool $boolean, string $service, object $override)
 */
final class ServiceOverrideProcessed extends ServiceOverrideEvent
{
    /**
     * @var string
     */
    public readonly string $service;

    /**
     * @var object<\Sprout\Contracts\ServiceOverride>
     * @phpstan-var ServiceOverrideClass
     */
    public readonly object $override;

    /**
     * @param string                                    $service
     * @param object<\Sprout\Contracts\ServiceOverride> $override
     *
     * @phpstan-param ServiceOverrideClass              $override
     */
    public function __construct(string $service, object $override)
    {
        $this->service  = $service;
        $this->override = $override;
    }
}
