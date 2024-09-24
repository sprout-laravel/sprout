<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Sprout\Attributes\TenantRelation;
use Sprout\Contracts\Tenancy;
use Sprout\Database\Eloquent\Contracts\OptionalTenant;
use Sprout\Managers\TenancyManager;

/**
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait IsTenantChild
{
    /**
     * @var string
     */
    protected static string $tenantRelationName;

    protected static bool $ignoreTenantRestrictions = false;

    public static function shouldIgnoreTenantRestrictions(): bool
    {
        return self::$ignoreTenantRestrictions;
    }

    public static function ignoreTenantRestrictions(): void
    {
        self::$ignoreTenantRestrictions = true;
    }

    public static function resetTenantRestrictions(): void
    {
        self::$ignoreTenantRestrictions = false;
    }

    public static function isTenantOptional(): bool
    {
        return is_subclass_of(static::class, OptionalTenant::class)
               || (
                   method_exists(static::class, 'shouldIgnoreTenantRestrictions')
                   && static::shouldIgnoreTenantRestrictions() // @phpstan-ignore-line
               );
    }

    private function findTenantRelationName(): string
    {
        try {
            $methods = collect((new ReflectionClass(static::class))->getMethods(ReflectionMethod::IS_PUBLIC))
                ->filter(function (ReflectionMethod $method) {
                    return ! $method->isStatic() && $method->getAttributes(TenantRelation::class);
                })
                ->map(fn (ReflectionMethod $method) => $method->getName());

            if ($methods->isEmpty()) {
                throw new RuntimeException('No tenant relation found in model [' . static::class . ']');
            }

            if ($methods->count() > 1) {
                throw new RuntimeException(
                    'Models can only have one tenant relation, [' . static::class . '] has ' . $methods->count()
                );
            }

            return $methods->first();
        } catch (ReflectionException $exception) {
            throw new RuntimeException('Unable to find tenant relation for model [' . static::class . ']', previous: $exception); // @codeCoverageIgnore
        }
    }

    public function getTenantRelationName(): ?string
    {
        if (! isset($this->tenantRelationNames[static::class])) {
            self::$tenantRelationName = $this->findTenantRelationName();
        }

        return self::$tenantRelationName ?? null;
    }

    public function getTenancyName(): ?string
    {
        return null;
    }

    public function getTenancy(): Tenancy
    {
        return app(TenancyManager::class)->get($this->getTenancyName());
    }

    public function getTenantRelation(): Relation
    {
        return $this->{$this->getTenantRelationName()}();
    }
}
