<?php
declare(strict_types=1);

namespace Sprout\Providers;

use Illuminate\Database\Eloquent\Model;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantHasResources;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Support\BaseTenantProvider;

/**
 * Eloquent Tenant Provider
 *
 * This class provides an implementation of the {@see \Sprout\Contracts\TenantProvider}
 * contract for situations where the {@see Tenant} is an
 * Eloquent model.
 *
 * @template TenantModel of \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant
 *
 * @extends \Sprout\Support\BaseTenantProvider<TenantModel>
 *
 * @internal New instances are created with {@see \Sprout\Managers\TenantProviderManager::createEloquentProvider()}, and shouldn't be created manually
 */
final class EloquentTenantProvider extends BaseTenantProvider
{
    /**
     * The model class
     *
     * @var class-string
     *
     * @phpstan-var class-string<TenantModel>
     */
    private string $modelClass;

    /**
     * A model instance to work from
     *
     * @var Model
     *
     * @phpstan-var TenantModel
     */
    private Model $model;

    /**
     * Create a new instance of the eloquent tenant provider
     *
     * @param string       $name
     * @param class-string $modelClass
     *
     * @phpstan-param class-string<TenantModel> $modelClass
     */
    public function __construct(string $name, string $modelClass)
    {
        parent::__construct($name);

        $this->modelClass = $modelClass;
    }

    /**
     * Get the model class
     *
     * @return string
     *
     * @phpstan-return class-string<TenantModel>
     */
    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * Retrieve a tenant by its identifier
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using an identifier.
     *
     * @param string $identifier
     *
     * @return Tenant|null
     *
     * @see Tenant::getTenantIdentifier()
     * @see Tenant::getTenantIdentifierName()
     *
     * @phpstan-return TenantModel|null
     */
    public function retrieveByIdentifier(string $identifier): ?Tenant
    {
        $model = $this->getModel();

        /** @var TenantModel|null */
        return $model->newModelQuery()
                     ->where($model->getTenantIdentifierName(), $identifier)
                     ->first();
    }

    /**
     * Retrieve a tenant by its key
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using a key.
     *
     * @param int|string $key
     *
     * @return Tenant|null
     *
     * @see Tenant::getTenantKey()
     * @see Tenant::getTenantKeyName()
     *
     * @phpstan-return TenantModel|null
     */
    public function retrieveByKey(int|string $key): ?Tenant
    {
        $model = $this->getModel();

        /** @var TenantModel|null */
        return $model->newModelQuery()
                     ->where($model->getTenantKeyName(), $key)
                     ->first();
    }

    /**
     * Retrieve a tenant by its resource key
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using a resource key.
     * The tenant class must implement the {@see TenantHasResources}
     * interface for this method to work.
     *
     * @param string $resourceKey
     *
     * @return (Tenant&TenantHasResources)|null
     *
     * @phpstan-return (TenantModel&TenantHasResources)|null
     *
     * @throws MisconfigurationException
     *
     * @see TenantHasResources::getTenantResourceKeyName()
     * @see TenantHasResources::getTenantResourceKey()
     */
    public function retrieveByResourceKey(string $resourceKey): (Tenant&TenantHasResources)|null
    {
        $model = $this->getModel();

        if (! $model instanceof TenantHasResources) {
            throw MisconfigurationException::misconfigured('tenant', $model::class, 'resources');
        }

        /** @var (TenantModel&TenantHasResources)|null */
        return $model->newModelQuery()
                     ->where($model->getTenantResourceKeyName(), $resourceKey)
                     ->first();
    }

    /**
     * Get an instance of the tenant model
     *
     * @return Model&Tenant
     *
     * @phpstan-return TenantModel
     */
    private function getModel(): Model&Tenant
    {
        if (! isset($this->model)) {
            $this->model = new $this->modelClass();
        }

        return $this->model;
    }
}
