<?php

namespace Sprout;

/**
 * Get the core sprout class
 *
 * @return \Sprout\Sprout
 */
function sprout(): Sprout
{
    return app(Sprout::class);
}
