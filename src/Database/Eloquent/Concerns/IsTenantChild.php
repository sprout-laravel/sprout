<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Sprout\Attributes\TenantRelation;
use Sprout\Contracts\TenantRelationHandler;
use Sprout\Database\Eloquent\Relations\BelongsToManyTenants;
use Sprout\Database\Eloquent\Relations\BelongsToTenant;
use Sprout\Database\Eloquent\Relations\HasManyTenants;
use Sprout\Database\Eloquent\Relations\HasOneTenant;
use Sprout\Database\Eloquent\TenantChildScope;
use Sprout\Managers\TenancyManager;
use Sprout\TenancyOptions;

/**
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait IsTenantChild
{
    /**
     * @var array<class-string<\Illuminate\Database\Eloquent\Model>, string>
     */
    protected static array $tenantRelationNames = [];

    public static function bootIsTenantChild(): void
    {
        // Automatically scope queries
        static::addGlobalScope(new TenantChildScope());

        // Some relations require population before saving to the database
        static::creating(self::onCreating(...));

        // Others require population to happen after
        static::created(self::onCreated(...));

        // Once retrieved, we can automatically handle that too
        static::retrieved(self::onRetrieved(...));
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\IsTenantChild $model
     *
     * @return void
     */
    private static function onCreating(Model $model): void
    {
        if (! $model->getTenantRelationHandler()->populateBeforePersisting()) {
            return;
        }

        /** @var \Sprout\Contracts\Tenancy<\Illuminate\Database\Eloquent\Model> $tenancy */
        $tenancy = app(TenancyManager::class)->get($model->getTenancyName());

        if ($tenancy->check()) {
            $model->getTenantRelationHandler()->populateRelation($model, $tenancy);
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\IsTenantChild $model
     *
     * @return void
     */
    private static function onCreated(Model $model): void
    {
        if ($model->getTenantRelationHandler()->populateBeforePersisting()) {
            return;
        }

        /** @var \Sprout\Contracts\Tenancy<\Illuminate\Database\Eloquent\Model> $tenancy */
        $tenancy = app(TenancyManager::class)->get($model->getTenancyName());

        if ($tenancy->check() && TenancyOptions::shouldHydrateTenantRelation($tenancy)) {
            $model->getTenantRelationHandler()->populateRelation($model, $tenancy);
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model&\Sprout\Database\Eloquent\Concerns\IsTenantChild $model
     *
     * @return void
     */
    private static function onRetrieved(Model $model): void
    {
        /** @var \Sprout\Contracts\Tenancy<\Illuminate\Database\Eloquent\Model> $tenancy */
        $tenancy = app(TenancyManager::class)->get($model->getTenancyName());

        if ($tenancy->check()) {
            $model->getTenantRelationHandler()->hydrateRelation($model, $tenancy);
        }
    }

    private TenantRelationHandler $tenantRelationHandler;

    private function findTenantRelationName(): string
    {
        try {
            $methods = collect((new ReflectionClass(static::class))->getMethods(ReflectionMethod::IS_PUBLIC))
                ->filter(function (ReflectionMethod $method) {
                    return ! $method->isStatic() && $method->getAttributes(TenantRelation::class);
                })
                ->map(fn (ReflectionMethod $method) => $method->getName());

            if ($methods->isEmpty()) {
                throw new RuntimeException('No tenant relation found in mode [' . static::class . ']');
            }

            if ($methods->count() > 1) {
                throw new RuntimeException(
                    'Models can only have one tenant relation, [' . static::class . '] has ' . $methods->count()
                );
            }

            return $methods->first();
        } catch (ReflectionException $exception) {
            throw new RuntimeException('Unable to find tenant relation for model [' . static::class . ']', previous: $exception);
        }
    }

    public function getTenantRelationName(): ?string
    {
        if (! isset($this->tenantRelationNames[static::class])) {
            self::$tenantRelationNames[static::class] = $this->findTenantRelationName();
        }

        return self::$tenantRelationNames[static::class] ?? null;
    }

    public function getTenancyName(): ?string
    {
        return null;
    }

    public function getTenantRelation(): Relation
    {
        return $this->{$this->getTenantRelationName()}();
    }

    public function getTenantRelationHandler(): TenantRelationHandler
    {
        if (! isset($this->tenantRelationHandler)) {
            $this->tenantRelationHandler = $this->createTenantRelationHandler();
        }

        return $this->tenantRelationHandler;
    }

    private function createTenantRelationHandler(): TenantRelationHandler
    {
        $relation = $this->getTenantRelation();

        // All but HasMany have a getRelationName() method, but for simplicity
        // we're going to use the one in this trait, as we know that's correct,
        // otherwise we wouldn't have the instance to check.

        if ($relation instanceof BelongsTo) {
            return new BelongsToTenant(static::class, $this->getTenantRelationName());
        }

        if ($relation instanceof BelongsToMany) {
            return new BelongsToManyTenants(static::class, $this->getTenantRelationName());
        }

        if ($relation instanceof HasOne) {
            return new HasOneTenant(static::class, $this->getTenantRelationName());
        }

        if ($relation instanceof HasMany) {
            return new HasManyTenants(static::class, $this->getTenantRelationName());
        }

        throw new RuntimeException(
            'Tenant relation for model [' . static::class . '] is of an unsupported type'
        );
    }
}
