<?php
declare(strict_types=1);

namespace Sprout\Support;

use Illuminate\Http\Request;
use Sprout\Contracts\IdentityResolver;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;

abstract class BaseIdentityResolver implements IdentityResolver
{
    /**
     * @var string
     */
    private string $name;

    /**
     * @var array<\Sprout\Support\ResolutionHook>
     */
    private array $hooks;

    /**
     * @param string                                $name
     * @param array<\Sprout\Support\ResolutionHook> $hooks
     */
    public function __construct(string $name, array $hooks = [])
    {
        $this->name  = $name;
        $this->hooks = empty($hooks) ? [ResolutionHook::Routing] : $hooks;
    }

    /**
     * Get the registered name of the resolver
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Perform setup actions for the tenant
     *
     * When a tenant is marked as the current tenant within a tenancy, this
     * method will be called to perform any necessary setup actions.
     * This method is also called if there is no current tenant, as there may
     * be actions needed.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant|null          $tenant
     *
     * @phpstan-param Tenant|null                    $tenant
     *
     * @return void
     */
    public function setup(Tenancy $tenancy, ?Tenant $tenant): void
    {
        // This is intentionally empty
    }

    /**
     * Can the resolver run on the request
     *
     * This method allows a resolver to prevent resolution with the request in
     * its current state, whether that means it's too early, or too late.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Illuminate\Http\Request               $request
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Support\ResolutionHook         $hook
     *
     * @return bool
     */
    public function canResolve(Request $request, Tenancy $tenancy, ResolutionHook $hook): bool
    {
        return ! $tenancy->wasResolved() && in_array($hook, $this->hooks, true);
    }
}
