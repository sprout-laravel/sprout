<?php
declare(strict_types=1);

namespace Sprout\Core\Support;

use Sprout\Core\Contracts\TenantProvider;

/**
 * Base Tenant Provider
 *
 * This is an abstract {@see \Sprout\Core\Contracts\TenantProvider} to provide
 * a shared implementation of common functionality.
 *
 * @template EntityClass of \Sprout\Core\Contracts\Tenant
 *
 * @implements \Sprout\Core\Contracts\TenantProvider<EntityClass>
 *
 * @package Core
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
