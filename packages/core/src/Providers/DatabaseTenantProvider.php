<?php
declare(strict_types=1);

namespace Sprout\Providers;

use Illuminate\Database\ConnectionInterface;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Support\BaseTenantProvider;
use Sprout\Support\GenericTenant;

/**
 * Database Tenant Provider
 *
 * This is an implementation of {@see \Sprout\Contracts\TenantProvider} that
 * uses Laravels base query builder.
 *
 * @package Core
 *
 * @template EntityClass of \Sprout\Contracts\Tenant
 *
 * @extends \Sprout\Support\BaseTenantProvider<EntityClass>
 */
final class DatabaseTenantProvider extends BaseTenantProvider
{
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
     * @phpstan-var class-string<EntityClass>
     */
    private string $entityClass;

    /**
     * @var \Sprout\Contracts\Tenant
     *
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
     * @phpstan-param class-string<EntityClass>        $entityClass
     */
    public function __construct(string $name, ConnectionInterface $connection, string $table, string $entityClass = GenericTenant::class)
    {
        parent::__construct($name);

        $this->connection  = $connection;
        $this->table       = $table;
        $this->entityClass = $entityClass;
    }

    /**
     * Get the database table name
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the entity class
     *
     * @return class-string
     *
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
     * @phpstan-return EntityClass|null
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
     * @phpstan-return EntityClass|null
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

    /**
     * Retrieve a tenant by its resource key
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using a resource key.
     * The tenant class must implement the {@see \Sprout\Contracts\TenantHasResources}
     * interface for this method to work.
     *
     * @param string $resourceKey
     *
     * @return (\Sprout\Contracts\Tenant&\Sprout\Contracts\TenantHasResources)|null
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     *
     * @phpstan-return (EntityClass&\Sprout\Contracts\TenantHasResources)|null
     *
     * @see \Sprout\Contracts\TenantHasResources::getTenantResourceKeyName()
     * @see \Sprout\Contracts\TenantHasResources::getTenantResourceKey()
     */
    public function retrieveByResourceKey(string $resourceKey): (Tenant&TenantHasResources)|null
    {
        $entity = $this->getEntity();

        if (! ($entity instanceof TenantHasResources)) {
            throw MisconfigurationException::misconfigured('tenant', $entity::class, 'resources');
        }

        $attributes = $this->connection->table($this->table)
                                       ->where($entity->getTenantResourceKeyName(), '=', $resourceKey)
                                       ->first();

        if ($attributes !== null) {
            return $this->makeEntity($attributes); // @phpstan-ignore-line
        }

        return null;
    }
}
