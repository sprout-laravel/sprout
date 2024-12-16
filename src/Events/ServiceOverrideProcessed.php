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
 * @method static self dispatch(object $override)
 * @method static self dispatchIf(bool $boolean, object $override)
 * @method static self dispatchUnless(bool $boolean, object $override)
 */
final class ServiceOverrideProcessed extends ServiceOverrideEvent
{
    /**
     * @var object<\Sprout\Contracts\ServiceOverride>
     * @phpstan-var ServiceOverrideClass
     */
    public readonly object $override;

    /**
     * @param object<\Sprout\Contracts\ServiceOverride> $override
     *
     * @phpstan-param ServiceOverrideClass              $override
     */
    public function __construct(object $override)
    {
        $this->override = $override;
    }
}
