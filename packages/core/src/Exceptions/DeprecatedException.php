<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

final class DeprecatedException extends SproutException
{
    public static function make(): self
    {
        return new self('This feature is deprecated');
    }
}
