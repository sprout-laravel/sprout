<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Concerns;

/**
 * @phpstan-require-implements \Sprout\Contracts\Tenant
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
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
        return $this->getAttribute($this->getTenantIdentifierName());
    }

    /**
     * Get the name of the tenant identifier
     *
     * Retrieve the storage name for the tenant identifier, whether that's an
     * attribute, column name, array key or something else.
     * Used primarily by {@see \Sprout\Contracts\TenantProvider}.
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
        return $this->getKey();
    }

    /**
     * Get the name of the tenant key
     *
     * Retrieve the storage name for the tenant key, whether that's an
     * attribute, column name, array key or something else.
     * Used primarily by {@see \Sprout\Contracts\TenantProvider}.
     *
     * @return string
     *
     * @infection-ignore-all
     */
    public function getTenantKeyName(): string
    {
        return $this->getKeyName();
    }
}
