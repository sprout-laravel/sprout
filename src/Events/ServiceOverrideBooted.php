<?php
declare(strict_types=1);

namespace Sprout\Events;

/**
 * Service Override Booted Event
 *
 * This event is dispatched after a service override has been booted.
 *
 * @template OverrideClass of \Sprout\Contracts\ServiceOverride
 *
 * @extends ServiceOverrideEvent<OverrideClass>
 *
 * @codeCoverageIgnore
 */
final class ServiceOverrideBooted extends ServiceOverrideEvent
{
}
