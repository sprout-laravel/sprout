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
        $manager = $container->make(TenancyManager::class);

        if (! ($manager instanceof TenancyManager)) {
            // We'll fail silently here...for reasons
            return null;
        }

        return $manager->get($this->tenancy)->tenant();
    }
}
