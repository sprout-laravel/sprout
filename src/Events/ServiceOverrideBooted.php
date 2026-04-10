<?php
declare(strict_types=1);

namespace Sprout\Core\Events;

/**
 * Service Override Booted Event
 *
 * This event is dispatched after a service override has been booted.
 *
 * @template OverrideClass of \Sprout\Core\Contracts\ServiceOverride
 *
 * @extends \Sprout\Core\Events\ServiceOverrideEvent<OverrideClass>
 *
 * @package Overrides
 *
 * @codeCoverageIgnore
 */
final class ServiceOverrideBooted extends ServiceOverrideEvent
{
}
