<?php
declare(strict_types=1);

namespace Sprout\Core\Database\Eloquent\Concerns;

/**
 * Is Tenant
 *
 * This trait provides a default implementation of the {@see \Sprout\Core\Contracts\Tenant}
 * interface to simplify the creation of tenant models.
 *
 * @phpstan-require-implements \Sprout\Core\Contracts\Tenant
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 *
 * @pacakge Database\Eloquent
 */
trait IsTenant
{
    /**
     * Get the tenant identifier
     *
     * Retrieve the identifier used to publicly identify the tenant.
     *
     * @return string
     *
     * @infection-ignore-all
     */
    public function getTenantIdentifier(): string
    {
        /** @phpstan-ignore return.type */
        return $this->getAttribute($this->getTenantIdentifierName());
    }

    /**
     * Get the name of the tenant identifier
     *
     * Retrieve the storage name for the tenant identifier, whether that's an
     * attribute, column name, array key or something else.
     * Used primarily by {@see \Sprout\Core\Contracts\TenantProvider}.
     *
     * @return string
     *
     * @infection-ignore-all
     */
    public function getTenantIdentifierName(): string
    {
        return 'identifier';
    }

    /**
     * Get the tenant key
     *
     * Retrieve the key used to identify a tenant internally.
     *
     * @return int|string
     *
     * @infection-ignore-all
     */
    public function getTenantKey(): int|string
    {
        /** @phpstan-ignore return.type */
        return $this->getKey();
    }

    /**
     * Get the name of the tenant key
     *
     * Retrieve the storage name for the tenant key, whether that's an
     * attribute, column name, array key or something else.
     * Used primarily by {@see \Sprout\Core\Contracts\TenantProvider}.
     *
     * @return string
     *
     * @infection-ignore-all
     */
    public function getTenantKeyName(): string
    {
        return $this->getKeyName(); // @codeCoverageIgnore
    }
}
