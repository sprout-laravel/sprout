<?php
declare(strict_types=1);

namespace Sprout\Attributes;

use Attribute;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Sprout\Contracts\Tenant;
use Sprout\Managers\TenancyManager;

/**
 * Current Tenant Attribute
 *
 * This is a contextual attribute that allows for the auto-injection of the
 * current tenant for the default, or a given tenancy.
 *
 * @see     https://laravel.com/docs/12.x/container#contextual-attributes
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class CurrentTenant implements ContextualAttribute
{
    /**
     * The tenancy to use
     *
     * @var string|null
     */
    public ?string $tenancy;

    /**
     * Create a new instance
     *
     * @param string|null $tenancy
     */
    public function __construct(?string $tenancy = null)
    {
        $this->tenancy = $tenancy;
    }

    /**
     * Resolve the tenant using this attribute
     *
     * @param CurrentTenant $tenant
     * @param Container     $container
     *
     * @return Tenant|null
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function resolve(self $tenant, Container $container): ?Tenant
    {
        /**
         * It's not nullable, it'll be an exception
         *
         * @noinspection NullPointerExceptionInspection
         */
        return $container->make(TenancyManager::class)->get($this->tenancy)->tenant();
    }
}
