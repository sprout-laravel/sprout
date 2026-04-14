<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

use RuntimeException;
use Sprout\Contracts\TenantConfigException;

final class CyclicOverrideException extends RuntimeException implements TenantConfigException
{
    public static function make(string $term, string $name): self
    {
        return new self(sprintf(
            'Attempt to create cyclic config %s [%s] detected',
            $term,
            $name,
        ));
    }
}
