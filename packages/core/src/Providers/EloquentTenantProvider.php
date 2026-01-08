<?php
declare(strict_types=1);

namespace Sprout\Core\Providers;

use Illuminate\Database\Eloquent\Model;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Contracts\TenantHasResources;
use Sprout\Core\Exceptions\MisconfigurationException;
use Sprout\Core\Support\BaseTenantProvider;

/**
 * Eloquent Tenant Provider
 *
 * This class provides an implementation of the {@see \Sprout\Core\Contracts\TenantProvider}
 * contract for situations where the {@see \Sprout\Core\Contracts\Tenant} is an
 * Eloquent model.
 *
 * @template TenantModel of \Illuminate\Database\Eloquent\Model&\Sprout\Core\Contracts\Tenant
 *
 * @extends \Sprout\Core\Support\BaseTenantProvider<TenantModel>
 *
 * @package  Providers
 *
 * @internal New instances are created with {@see \Sprout\Core\Managers\TenantProviderManager::createEloquentProvider()}, and shouldn't be created manually
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
     * @var \Illuminate\Database\Eloquent\Model
     *
     * @phpstan-var TenantModel
     */
    private Model $model;

    /**
     * Create a new instance of the eloquent tenant provider
     *
     * @param string                            $name
     * @param class-string                      $modelClass
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
     * Get an instance of the tenant model
     *
     * @return \Illuminate\Database\Eloquent\Model&\Sprout\Core\Contracts\Tenant
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

    /**
     * Retrieve a tenant by its identifier
     *
     * Gets an instance of the tenant implementation the provider represents,
     * using an identifier.
     *
     * @param string $identifier
     *
     * @return \Sprout\Core\Contracts\Tenant|null
     *
     * @see \Sprout\Core\Contracts\Tenant::getTenantIdentifier()
     * @see \Sprout\Core\Contracts\Tenant::getTenantIdentifierName()
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
     * @return \Sprout\Core\Contracts\Tenant|null
     *
     * @see \Sprout\Core\Contracts\Tenant::getTenantKey()
     * @see \Sprout\Core\Contracts\Tenant::getTenantKeyName()
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
     * The tenant class must implement the {@see \Sprout\Core\Contracts\TenantHasResources}
     * interface for this method to work.
     *
     * @param string $resourceKey
     *
     * @return (\Sprout\Core\Contracts\Tenant&\Sprout\Core\Contracts\TenantHasResources)|null
     *
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     *
     * @phpstan-return (TenantModel&\Sprout\Core\Contracts\TenantHasResources)|null
     *
     * @see \Sprout\Core\Contracts\TenantHasResources::getTenantResourceKeyName()
     * @see \Sprout\Core\Contracts\TenantHasResources::getTenantResourceKey()
     */
    public function retrieveByResourceKey(string $resourceKey): (Tenant&TenantHasResources)|null
    {
        $model = $this->getModel();

        if (! ($model instanceof TenantHasResources)) {
            throw MisconfigurationException::misconfigured('tenant', $model::class, 'resources');
        }

        /** @var (TenantModel&\Sprout\Core\Contracts\TenantHasResources)|null */
        return $model->newModelQuery()
                     ->where($model->getTenantResourceKeyName(), $resourceKey)
                     ->first();
    }
}
