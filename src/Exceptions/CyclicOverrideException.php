<?php
declare(strict_types=1);

namespace Sprout\Bud\Exceptions;

final class CyclicOverrideException extends BudException
{
    public static function make(string $term, string $name): self
    {
        return new self(sprintf(
            'Attempt to create cyclic bud %s [%s] detected',
            $term,
            $name
        ));
    }
}
