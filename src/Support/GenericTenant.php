<?php
declare(strict_types=1);

namespace Sprout\Support;

use Sprout\Contracts\Tenant;

class GenericTenant implements Tenant
{
    /**
     * All the tenant's attributes.
     *
     * @var array<string, mixed>
     */
    protected array $attributes;

    /**
     * Create a new generic User object.
     *
     * @param array<string, mixed> $attributes
     *
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Get the tenant identifier
     *
     * Retrieve the identifier used to publicly identify the tenant.
     *
     * @return string
     */
    public function getTenantIdentifier(): string
    {
        /** @phpstan-ignore-next-line */
        return $this->attributes[$this->getTenantIdentifierName()];
    }

    /**
     * Get the name of the tenant identifier
     *
     * Retrieve the storage name for the tenant identifier, whether that's an
     * attribute, column name, array key or something else.
     * Used primarily by {@see \Sprout\Contracts\TenantProvider}.
     *
     * @return string
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
     */
    public function getTenantKey(): int|string
    {
        /** @phpstan-ignore-next-line */
        return $this->attributes[$this->getTenantKeyName()];
    }

    /**
     * Get the name of the tenant key
     *
     * Retrieve the storage name for the tenant key, whether that's an
     * attribute, column name, array key or something else.
     * Used primarily by {@see \Sprout\Contracts\TenantProvider}.
     *
     * @return string
     */
    public function getTenantKeyName(): string
    {
        return 'id';
    }

    /**
     * Dynamically access the tenant's attributes.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->attributes[$key];
    }

    /**
     * Dynamically set an attribute on the tenant.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Dynamically check if a value is set on the tenant.
     *
     * @param string $key
     *
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Dynamically unset a value on the tenant.
     *
     * @param string $key
     *
     * @return void
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }
}
