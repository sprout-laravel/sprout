<?php
declare(strict_types=1);

namespace Sprout\Support;

use Sprout\Contracts\TenantProvider;

/**
 * @template EntityClass of \Sprout\Contracts\Tenant
 *
 * @implements \Sprout\Contracts\TenantProvider<EntityClass>
 */
abstract class BaseTenantProvider implements TenantProvider
{
    /**
     * @var string
     */
    private string $name;

    /**
     * Create a new instance of the tenant provider
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get the registered name of the provider
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}
