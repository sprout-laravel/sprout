<?php
declare(strict_types=1);

namespace Sprout\Providers;

use Illuminate\Database\ConnectionInterface;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantProvider;
use Sprout\Support\GenericTenant;

/**
 * @template EntityClass of \Sprout\Contracts\Tenant
 *
 * @implements \Sprout\Contracts\TenantProvider<EntityClass>
 */
final class DatabaseTenantProvider implements TenantProvider
{
    /**
     * @var string
     */
    private string $name;

    /**
     * @var \Illuminate\Database\ConnectionInterface
     */
    private ConnectionInterface $connection;

    /**
     * @var string
     */
    private string $table;

    /**
     * @var class-string<\Sprout\Contracts\Tenant>
     *
     * @psalm-var class-string<EntityClass>
     * @phpstan-var class-string<EntityClass>
     */
    private string $entityClass;

    /**
     * @var \Sprout\Contracts\Tenant
     *
     * @psalm-var EntityClass
     * @phpstan-var EntityClass
     */
    private Tenant $entity;

    /**
     * Create a new instance of the database tenant provider
     *
     * @param string                                   $name
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param string                                   $table
     * @param class-string<\Sprout\Contracts\Tenant>   $entityClass
     *
     * @psalm-param class-string<EntityClass>          $entityClass
     * @phpstan-param class-string<EntityClass>        $entityClass
     */
    public function __construct(string $name, ConnectionInterface $connection, string $table, string $entityClass = GenericTenant::class)
    {
        $this->name        = $name;
        $this->connection  = $connection;
        $this->table       = $table;
        $this->entityClass = $entityClass;
    }

    /**
     * Get the registered name of the provider
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the entity class
     *
     * @return class-string
     *
     * @psalm-return class-string<EntityClass>
     * @phpstan-return class-string<EntityClass>
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * Get an instance of the tenant entity
     *
     * @return \Sprout\Contracts\Tenant
     *
     * @psalm-return EntityClass
     * @phpstan-return EntityClass
     */
    private function getEntity(): Tenant
    {
        if (! isset($this->entity)) {
            $this->entity = new $this->entityClass();
        }

        return $this->entity;
    }

    /**
     * Make a new instance of the entity class
     *
     * @param array<string, mixed>|object $attributes
     *
     * @return object
     *
     * @psalm-return EntityClass
     * @phpstan-return EntityClass
     */
    private function makeEntity(array|object $attributes): object
    {
        return new $this->entityClass((array)$attributes);
    }

    /**
     * Retrieve a tenant by its identifier
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using an identifier.
     *
     * @param string $identifier
     *
     * @return \Sprout\Contracts\Tenant|null
     *
     * @see \Sprout\Contracts\Tenant::getTenantIdentifier()
     * @see \Sprout\Contracts\Tenant::getTenantIdentifierName()
     *
     * @psalm-return Tenant|null
     * @phpstan-return Tenant|null
     */
    public function retrieveByIdentifier(string $identifier): ?Tenant
    {
        $entity     = $this->getEntity();
        $attributes = $this->connection->table($this->table)
                                       ->where($entity->getTenantIdentifierName(), '=', $identifier)
                                       ->first();

        if ($attributes !== null) {
            return $this->makeEntity($attributes);
        }

        return null;
    }

    /**
     * Retrieve a tenant by its key
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using a key.
     *
     * @param int|string $key
     *
     * @return \Sprout\Contracts\Tenant|null
     *
     * @see \Sprout\Contracts\Tenant::getTenantKey()
     * @see \Sprout\Contracts\Tenant::getTenantKeyName()
     *
     * @psalm-return Tenant|null
     * @phpstan-return Tenant|null
     */
    public function retrieveByKey(int|string $key): ?Tenant
    {
        $entity     = $this->getEntity();
        $attributes = $this->connection->table($this->table)
                                       ->where($entity->getTenantKeyName(), '=', $key)
                                       ->first();

        if ($attributes !== null) {
            return $this->makeEntity($attributes);
        }

        return null;
    }
}
