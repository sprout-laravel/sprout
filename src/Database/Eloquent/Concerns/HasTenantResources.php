<?php
declare(strict_types=1);

namespace Sprout\Core\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Sprout\Core\Contracts\TenantHasResources;

/**
 * Has Tenant Resources
 *
 * This trait provides helper methods alongside default implementations and
 * functionality to support a {@see \Sprout\Core\Contracts\Tenant} model that also
 * implements the {@see \Sprout\Core\Contracts\TenantHasResources} interface.
 *
 * @phpstan-require-implements \Sprout\Core\Contracts\Tenant
 * @phpstan-require-implements \Sprout\Core\Contracts\TenantHasResources
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 *
 * @package        Database\Eloquent
 *
 * @phpstan-ignore trait.unused
 */
trait HasTenantResources
{
    /**
     * Boot the trait
     *
     * @return void
     */
    public static function bootHasTenantResources(): void
    {
        static::creating(static function (Model&TenantHasResources $model) {
            if ($model->getAttribute($model->getTenantResourceKeyName()) === null) {
                $model->setAttribute(
                    $model->getTenantResourceKeyName(),
                    method_exists($model, 'generateNewResourceKey')
                        ? $model->generateNewResourceKey()
                        : Str::uuid()
                );
            }
        });
    }

    /**
     * Generate a new resource key
     *
     * @return mixed
     */
    public function generateNewResourceKey(): mixed
    {
        return Str::uuid();
    }

    /**
     * Get the resource key used to identify the tenants resources
     *
     * @return string
     */
    public function getTenantResourceKey(): string
    {
        return (string)$this->getAttribute($this->getTenantResourceKeyName());
    }

    /**
     * Gets the name of the resource key used to identify the tenants resources
     *
     * @return string
     */
    public function getTenantResourceKeyName(): string
    {
        return 'resource_key';
    }
}
