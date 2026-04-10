<?php
declare(strict_types=1);

namespace Sprout\Core\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Sprout\Core\Attributes\TenantRelation;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Database\Eloquent\Contracts\OptionalTenant;
use Sprout\Core\Exceptions\TenantRelationException;
use Sprout\Core\Managers\TenancyManager;

/**
 * Is Tenant Child
 *
 * This trait provides helper methods and functionality that supports the
 * automatic handling of Eloquent models that are direct descendants of
 * {@see \Sprout\Core\Contracts\Tenant} models.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @package        Database\Eloquent
 *
 * @phpstan-ignore trait.unused
 */
trait IsTenantChild
{
    /**
     * The name of the tenant relation
     *
     * @var string
     */
    protected static string $tenantRelationName;

    /**
     * Whether to ignore tenant ownership restrictions
     *
     * @var bool
     */
    protected static bool $ignoreTenantRestrictions = false;

    /**
     * Check if tenant ownership restrictions should be ignored
     *
     * @return bool
     */
    public static function shouldIgnoreTenantRestrictions(): bool
    {
        return self::$ignoreTenantRestrictions;
    }

    /**
     * Enable the ignoring of tenant ownership restrictions
     *
     * @return void
     */
    public static function ignoreTenantRestrictions(): void
    {
        self::$ignoreTenantRestrictions = true;
    }

    /**
     * Disable the ignoring of tenant ownership restrictions
     *
     * @return void
     */
    public static function resetTenantRestrictions(): void
    {
        self::$ignoreTenantRestrictions = false;
    }

    /**
     * Temporarily disable tenant ownership restrictions and run the provided callback
     *
     * @template RetType of mixed
     *
     * @param callable(): RetType $callback
     *
     * @return mixed
     *
     * @phpstan-return RetType
     */
    public static function withoutTenantRestrictions(callable $callback): mixed
    {
        self::ignoreTenantRestrictions();

        $return = $callback();

        self::resetTenantRestrictions();

        return $return;
    }

    /**
     * Check if the model can function without a tenant
     *
     * @return bool
     *
     * @see \Sprout\Core\Database\Eloquent\Contracts\OptionalTenant
     */
    public static function isTenantOptional(): bool
    {
        return is_subclass_of(static::class, OptionalTenant::class);
    }

    /**
     * Attempt to find the name of the tenant relation
     *
     * @return string
     *
     * @throws \Sprout\Core\Exceptions\TenantRelationException
     *
     * @see \Sprout\Core\Attributes\TenantRelation
     */
    private function findTenantRelationName(): string
    {
        try {
            $methods = collect((new ReflectionClass(static::class))->getMethods(ReflectionMethod::IS_PUBLIC))
                ->filter(function (ReflectionMethod $method) {
                    return ! $method->isStatic() && $method->getAttributes(TenantRelation::class);
                })
                ->map(fn (ReflectionMethod $method) => $method->getName());

            if ($methods->isEmpty()) {
                throw TenantRelationException::missing(static::class);
            }

            if ($methods->count() > 1) {
                throw TenantRelationException::tooMany(static::class, $methods->count());
            }

            return $methods->first();
        } catch (ReflectionException $exception) {
            throw TenantRelationException::missing(static::class, previous: $exception); // @codeCoverageIgnore
        }
    }

    /**
     * Get the name of the tenant relation
     *
     * @return string|null
     *
     * @throws \Sprout\Core\Exceptions\TenantRelationException
     */
    public function getTenantRelationName(): ?string
    {
        if (! isset(static::$tenantRelationName)) {
            static::$tenantRelationName = $this->findTenantRelationName();
        }

        return static::$tenantRelationName ?? null;
    }

    /**
     * Get the name of the tenancy this model relates to a tenant of
     *
     * @return string|null
     */
    public function getTenancyName(): ?string
    {
        return null;
    }

    /**
     * Get the tenancy this model relates to a tenant of
     *
     * @return \Sprout\Core\Contracts\Tenancy
     */
    public function getTenancy(): Tenancy
    {
        /** @var \Sprout\Core\Managers\TenancyManager $tenancyManager */
        $tenancyManager = app(TenancyManager::class);

        return $tenancyManager->get($this->getTenancyName());
    }

    /**
     * Get the tenant relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function getTenantRelation(): Relation
    {
        return $this->{$this->getTenantRelationName()}();
    }
}
