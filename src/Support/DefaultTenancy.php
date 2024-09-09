<?php
/** @noinspection PhpUnnecessaryStaticReferenceInspection */
declare(strict_types=1);

namespace Sprout\Support;

use Sprout\Contracts\IdentityResolver;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Contracts\TenantProvider;
use Sprout\Events\CurrentTenantChanged;
use Sprout\Events\TenantIdentified;
use Sprout\Events\TenantLoaded;

/**
 * @template TenantClass of \Sprout\Contracts\Tenant
 *
 * @implements \Sprout\Contracts\Tenancy<TenantClass>
 */
final class DefaultTenancy implements Tenancy
{
    private string $name;

    /**
     * @var \Sprout\Contracts\TenantProvider<TenantClass>
     */
    private TenantProvider $provider;

    /**
     * @var \Sprout\Contracts\IdentityResolver|null
     */
    private ?IdentityResolver $resolver;

    /**
     * @var \Sprout\Contracts\Tenant|null
     *
     * @phpstan-var TenantClass|null
     */
    private ?Tenant $tenant = null;

    /**
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * @param string                                        $name
     * @param \Sprout\Contracts\TenantProvider<TenantClass> $provider
     * @param array<string, mixed>                          $options
     */
    public function __construct(string $name, TenantProvider $provider, array $options)
    {
        $this->name     = $name;
        $this->provider = $provider;
        $this->options  = $options;
    }

    /**
     * Get the registered name of the tenancy
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if there's a current tenant
     *
     * Checks to see if a current tenant is set.
     * Implementations should not attempt to load a tenant if one is not
     * present, but should perform a simple check for the present of a
     * tenant.
     *
     * @return bool
     */
    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Get the current tenant
     *
     * Gets the current set tenant if one is present.
     * Implementations may attempt to load a tenant if one isn't present, though
     * this is not required.
     *
     * @return \Sprout\Contracts\Tenant|null
     *
     * @phpstan-return TenantClass|null
     */
    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Get the tenants key
     *
     * Get the tenant key for the current tenant if there is one.
     *
     * @return int|string|null
     *
     * @see \Sprout\Contracts\Tenant::getTenantKey()
     */
    public function key(): int|string|null
    {
        return $this->tenant()?->getTenantKey();
    }

    /**
     * Get the tenants' identifier
     *
     * Get the tenant identifier for the current tenant if there is one.
     *
     * @return string|null
     *
     * @see \Sprout\Contracts\Tenant::getTenantIdentifier()
     */
    public function identifier(): ?string
    {
        return $this->tenant()?->getTenantIdentifier();
    }

    /**
     * Identity a tenant
     *
     * Retrieve and set the current tenant based on an identifier.
     *
     * @param string $identifier
     *
     * @return bool
     */
    public function identify(string $identifier): bool
    {
        $tenant = $this->provider()->retrieveByIdentifier($identifier);

        if ($tenant === null) {
            $this->resolver = null;

            return false;
        }

        $this->setTenant($tenant);

        event(new TenantIdentified($tenant, $this));

        return true;
    }

    /**
     * Load a tenant
     *
     * Retrieve and set the current tenant based on a key.
     *
     * @param int|string $key
     *
     * @return bool
     */
    public function load(int|string $key): bool
    {
        $tenant = $this->provider()->retrieveByKey($key);

        if ($tenant === null) {
            return false;
        }

        $this->setTenant($tenant);

        event( new TenantLoaded($tenant, $this));

        return true;
    }

    /**
     * Get the tenant provider
     *
     * Get the tenant provider used by this tenancy.
     *
     * @return \Sprout\Contracts\TenantProvider<TenantClass>
     */
    public function provider(): TenantProvider
    {
        return $this->provider;
    }

    /**
     * Set the identity resolved used
     *
     * @param \Sprout\Contracts\IdentityResolver $resolver
     *
     * @return static
     */
    public function resolvedVia(IdentityResolver $resolver): static
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Get the used identity resolver
     *
     * @return \Sprout\Contracts\IdentityResolver|null
     */
    public function resolver(): ?IdentityResolver
    {
        return $this->resolver;
    }

    /**
     * Check if the current tenant was resolved
     *
     * @return bool
     */
    public function wasResolved(): bool
    {
        return $this->check() && $this->resolver() !== null;
    }

    /**
     * Set the current tenant
     *
     * @param \Sprout\Contracts\Tenant|null $tenant
     *
     * @phpstan-param TenantClass|null      $tenant
     *
     * @return static
     */
    public function setTenant(?Tenant $tenant): static
    {
        $previousTenant = $this->tenant();

        if ($previousTenant !== $tenant) {
            $this->tenant = $tenant;

            event(new CurrentTenantChanged($this, $previousTenant, $tenant));
        }

        return $this;
    }

    /**
     * Get all tenant options
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Get a tenant option
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options()[$key] ?? $default;
    }
}
