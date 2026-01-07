<?php

namespace Sprout\Contracts;

/**
 * Tenant Contract
 *
 * This contract marks a class as being a tenant, and enforces the existence of
 * a tenant identifier and tenant key.
 *
 * @package Core
 */
interface Tenant
{
    /**
     * Get the tenant identifier
     *
     * Retrieve the identifier used to publicly identify the tenant.
     *
     * @return string
     */
    public function getTenantIdentifier(): string;

    /**
     * Get the name of the tenant identifier
     *
     * Retrieve the storage name for the tenant identifier, whether that's an
     * attribute, column name, array key or something else.
     * Used primarily by {@see \Sprout\Contracts\TenantProvider}.
     *
     * @return string
     */
    public function getTenantIdentifierName(): string;

    /**
     * Get the tenant key
     *
     * Retrieve the key used to identify a tenant internally.
     *
     * @return int|string
     */
    public function getTenantKey(): int|string;

    /**
     * Get the name of the tenant key
     *
     * Retrieve the storage name for the tenant key, whether that's an
     * attribute, column name, array key or something else.
     * Used primarily by {@see \Sprout\Contracts\TenantProvider}.
     *
     * @return string
     */
    public function getTenantKeyName(): string;
}
