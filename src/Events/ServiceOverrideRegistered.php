<?php
declare(strict_types=1);

namespace Sprout\Events;

/**
 * Service Override Registered Event
 *
 * This event is dispatched when a service override is registered with
 * Sprout.
 *
 * @template OverrideClass of \Sprout\Contracts\ServiceOverride
 *
 * @extends ServiceOverrideEvent<OverrideClass>
 *
 * @codeCoverageIgnore
 */
final class ServiceOverrideRegistered extends ServiceOverrideEvent
{
}
