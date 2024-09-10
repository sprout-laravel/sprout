<?php
declare(strict_types=1);

namespace Sprout\Attributes;

use Attribute;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Sprout\Contracts\Tenant;
use Sprout\Managers\TenancyManager;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class CurrentTenant implements ContextualAttribute
{
    public ?string $tenancy;

    public function __construct(?string $tenancy = null)
    {
        $this->tenancy = $tenancy;
    }

    public function resolve(CurrentTenant $tenant, Container $container): ?Tenant
    {
        /**
         * It's not nullable, it'll be an exception
         *
         * @noinspection NullPointerExceptionInspection
         */
        return $container->make(TenancyManager::class)->get($this->tenancy)->tenant();
    }
}
