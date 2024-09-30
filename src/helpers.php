<?php

namespace Sprout;

/**
 * Get the core sprout class
 *
 * @return \Sprout\Sprout
 */
function sprout(): Sprout
{
    return app()->make(Sprout::class);
}
