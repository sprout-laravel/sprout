<?php
declare(strict_types=1);

namespace Sprout\Core\Http\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Contracts\Tenant;
use Sprout\Core\Exceptions\CompatibilityException;
use Sprout\Core\Support\BaseIdentityResolver;
use Sprout\Core\Support\PlaceholderHelper;
use Sprout\Core\Support\ResolutionHook;
use Sprout\Core\TenancyOptions;

/**
 * Session Identity Resolver
 *
 * This class is responsible for resolving tenant identities from the current
 * request using the session.
 *
 * @package Http\Resolvers
 */
final class SessionIdentityResolver extends BaseIdentityResolver
{
    /**
     * The name of the session
     *
     * @var string
     */
    private string $session;

    /**
     * Create a new instance
     *
     * @param string      $name
     * @param string|null $session
     */
    public function __construct(string $name, ?string $session = null)
    {
        parent::__construct($name, [ResolutionHook::Middleware]);

        $this->session = $session ?? 'multitenancy.{tenancy}';
    }

    /**
     * Get the name of the session
     *
     * @return string
     */
    public function getSessionName(): string
    {
        return $this->session;
    }

    /**
     * Get the session name with replacements
     *
     * This method returns the name of the header returned by
     * {@see self::getSessionName()}, except it replaces <code>{tenancy}</code>
     * and <code>{resolver}</code> with the name of the tenancy, and resolver,
     * respectively.
     *
     * You can use an uppercase character for the first character, <code>{Tenancy}</code>
     * and <code>{Resolver}</code>, and it'll be run through {@see \ucfirst()}.
     *
     * @param \Sprout\Core\Contracts\Tenancy<*> $tenancy
     *
     * @return string
     */
    public function getRequestSessionName(Tenancy $tenancy): string
    {
        return PlaceholderHelper::replace(
            $this->getSessionName(),
            [
                'tenancy'  => $tenancy->getName(),
                'resolver' => $this->getName(),
            ]
        );
    }

    /**
     * Get an identifier from the request
     *
     * Locates a tenant identifier within the provided request and returns it.
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Illuminate\Http\Request                    $request
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     *
     * @return string|null
     *
     * @throws \Sprout\Core\Exceptions\CompatibilityException
     */
    public function resolveFromRequest(Request $request, Tenancy $tenancy): ?string
    {
        if (TenancyOptions::shouldEnableOverride($tenancy, 'session')) {
            throw CompatibilityException::make('resolver', $this->getName(), 'service override', 'session');
        }

        /**
         * This is unfortunately here because of the ludicrous return type
         *
         * @var string|null $identity
         */
        $identity = $request->session()->get($this->getRequestSessionName($tenancy));

        return $identity;
    }

    /**
     * Perform setup actions for the tenant
     *
     * When a tenant is marked as the current tenant within a tenancy, this
     * method will be called to perform any necessary setup actions.
     * This method is also called if there is no current tenant, as there may
     * be actions needed.
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Contracts\Tenant|null          $tenant
     *
     * @phpstan-param Tenant|null                         $tenant
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function setup(Tenancy $tenancy, ?Tenant $tenant): void
    {
        if ($tenant !== null && $tenancy->check()) {
            $this->getApp()
                 ->make(SessionManager::class)
                 ->put($this->getRequestSessionName($tenancy), $tenant->getTenantIdentifier());
        } else if ($tenant === null) {
            $this->getApp()
                 ->make(SessionManager::class)
                 ->forget($this->getRequestSessionName($tenancy));
        }
    }

    /**
     * Can the resolver run on the request
     *
     * This method allows a resolver to prevent resolution with the request in
     * its current state, whether that means it's too early, or too late.
     *
     * @template TenantClass of \Sprout\Core\Contracts\Tenant
     *
     * @param \Illuminate\Http\Request                    $request
     * @param \Sprout\Core\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Core\Support\ResolutionHook         $hook
     *
     * @return bool
     */
    public function canResolve(Request $request, Tenancy $tenancy, ResolutionHook $hook): bool
    {
        return ! $tenancy->wasResolved() && $request->hasSession() && $hook === ResolutionHook::Middleware;
    }
}
