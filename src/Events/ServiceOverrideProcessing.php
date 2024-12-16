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
 * @method static self dispatch(string $override)
 * @method static self dispatchIf(bool $boolean, string $override)
 * @method static self dispatchUnless(bool $boolean, string $override)
 */
final class ServiceOverrideProcessing extends ServiceOverrideEvent
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
