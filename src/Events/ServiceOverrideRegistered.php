<?php
declare(strict_types=1);

namespace Sprout\Events;

/**
 * Service Override Registered Event
 *
 * This event is dispatched when a service override is registered with
 * Sprout.
 *
 * @package Overrides
 *
 * @method static self dispatch(string $override)
 * @method static self dispatchIf(bool $boolean, string $override)
 * @method static self dispatchUnless(bool $boolean, string $override)
 */
final class ServiceOverrideRegistered extends ServiceOverrideEvent
{
    /**
     * @var class-string<\Sprout\Contracts\ServiceOverride>
     */
    public readonly string $override;

    /**
     * @param class-string<\Sprout\Contracts\ServiceOverride> $override
     */
    public function __construct(string $override)
    {
        $this->override = $override;
    }
}
