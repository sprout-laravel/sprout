<?php
declare(strict_types=1);

namespace Sprout\Core\Events;

/**
 * Service Override Registered Event
 *
 * This event is dispatched when a service override is registered with
 * Sprout.
 *
 * @template OverrideClass of \Sprout\Core\Contracts\ServiceOverride
 *
 * @extends \Sprout\Core\Events\ServiceOverrideEvent<OverrideClass>
 *
 * @package Overrides
 *
 * @codeCoverageIgnore
 */
final class ServiceOverrideRegistered extends ServiceOverrideEvent
{
}
