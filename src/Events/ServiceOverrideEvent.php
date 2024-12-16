<?php
declare(strict_types=1);

namespace Sprout\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Service Override Event
 *
 * This is a base event class for the service override events.
 *
 * @package Overrides
 */
abstract class ServiceOverrideEvent
{
    use Dispatchable;
}
