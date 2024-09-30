<?php
declare(strict_types=1);

namespace Sprout\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Sprout\Contracts\TenantHasResources;

/**
 * @phpstan-require-implements \Sprout\Contracts\Tenant
 * @phpstan-require-implements \Sprout\Contracts\TenantHasResources
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait HasTenantResources
{
    public static function bootHasTenantResources(): void
    {
        static::creating(static function (Model&TenantHasResources $model) {
            if ($model->getAttribute($model->getTenantResourceKeyName()) === null) {
                $model->setAttribute(
                    $model->getTenantResourceKeyName(),
                    Str::uuid()
                );
            }
        });
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
