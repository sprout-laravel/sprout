<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

final class NoTenantFound extends SproutException
{
    public static function make(string $resolver, string $tenancy, ?string $identity = null): self
    {
        return new self(
            $identity
                ? 'No valid tenant [' . $tenancy . '] found for \'' . $identity . '\', resolved via [' . $resolver . ']'
                : 'No valid tenant [' . $tenancy . '] found [' . $resolver . ']'
        );
    }
}
