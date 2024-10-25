<?php
declare(strict_types=1);

namespace Sprout\Exceptions;

use Throwable;

/**
 * Tenant Relation Exception
 *
 * This exception is used for issues that arise with tenant child/descendant
 * relations.
 *
 * @package Database\Eloquent
 */
final class TenantRelationException extends SproutException
{
    /**
     * Create an exception for when the tenant relation is missing
     *
     * @param string          $model
     * @param \Throwable|null $previous
     *
     * @return self
     */
    public static function missing(string $model, ?Throwable $previous = null): self
    {
        return new self('Cannot find tenant relation for model [' . $model . ']', previous: $previous);
    }

    /**
     * Create an exception for when there are too many tenant relations
     *
     * @param string $model
     * @param int    $count
     *
     * @return self
     */
    public static function tooMany(string $model, int $count): self
    {
        return new self('Expected one tenant relation, found ' . $count . ' in model [' . $model . ']');
    }
}
