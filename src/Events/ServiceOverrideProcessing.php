<?php
declare(strict_types=1);

namespace Sprout\Events;

/**
 * Service Override Processing Event
 *
 * This event is dispatched before a service override is processed.
 *
 * @package Overrides
 *
 * @method static self dispatch(string $service, string $override)
 * @method static self dispatchIf(bool $boolean, string $service, string $override)
 * @method static self dispatchUnless(bool $boolean, string $service, string $override)
 */
final class ServiceOverrideProcessing extends ServiceOverrideEvent
{
    /**
     * @var string
     */
    public readonly string $service;

    /**
     * @var class-string<\Sprout\Contracts\ServiceOverride>
     */
    public readonly string $override;

    /**
     * @param string                                          $service
     * @param class-string<\Sprout\Contracts\ServiceOverride> $override
     */
    public function __construct(string $service, string $override)
    {
        $this->service  = $service;
        $this->override = $override;
    }
}
