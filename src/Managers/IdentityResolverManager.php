<?php
declare(strict_types=1);

namespace Sprout\Managers;

use Sprout\Exceptions\MisconfigurationException;
use Sprout\Http\Resolvers\CookieIdentityResolver;
use Sprout\Http\Resolvers\HeaderIdentityResolver;
use Sprout\Http\Resolvers\PathIdentityResolver;
use Sprout\Http\Resolvers\SessionIdentityResolver;
use Sprout\Http\Resolvers\SubdomainIdentityResolver;
use Sprout\Support\BaseFactory;

/**
 * Identity Resolver Manager
 *
 * This is a manager and factory, responsible for creating and storing
 * implementations of {@see \Sprout\Contracts\IdentityResolver}.
 *
 * @extends \Sprout\Support\BaseFactory<\Sprout\Contracts\IdentityResolver>
 *
 * @package Core
 */
final class IdentityResolverManager extends BaseFactory
{
    /**
     * Get the name used by this factory
     *
     * @return string
     */
    public function getFactoryName(): string
    {
        return 'resolver';
    }

    /**
     * Get the config key for the given name
     *
     * @param string $name
     *
     * @return string
     */
    public function getConfigKey(string $name): string
    {
        return 'multitenancy.resolvers.' . $name;
    }

    /**
     * Create the subdomain identity resolver
     *
     * @param array<string, mixed>                                                                                                          $config
     * @param string                                                                                                                        $name
     *
     * @phpstan-param array{domain?: string, pattern?: string|null, parameter?: string|null, hooks?: array<\Sprout\Support\ResolutionHook>} $config
     *
     * @return \Sprout\Http\Resolvers\SubdomainIdentityResolver
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    protected function createSubdomainResolver(array $config, string $name): SubdomainIdentityResolver
    {
        if (! isset($config['domain'])) {
            throw MisconfigurationException::missingConfig('domain', 'resolver', $name);
        }

        return new SubdomainIdentityResolver(
            $name,
            $config['domain'],
            $config['pattern'] ?? null,
            $config['parameter'] ?? null,
            $config['hooks'] ?? []
        );
    }

    /**
     * Create the path identity resolver
     *
     * @param array<string, mixed>                                                                                                             $config
     * @param string                                                                                                                           $name
     *
     * @phpstan-param array{segment?: int|null, pattern?: string|null, parameter?: string|null, hooks?: array<\Sprout\Support\ResolutionHook>} $config
     *
     * @return \Sprout\Http\Resolvers\PathIdentityResolver
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    protected function createPathResolver(array $config, string $name): PathIdentityResolver
    {
        $segment = $config['segment'] ?? 1;

        if ($segment < 1) {
            throw MisconfigurationException::invalidConfig('segment', 'resolver', $name);
        }

        return new PathIdentityResolver(
            $name,
            $segment,
            $config['pattern'] ?? null,
            $config['parameter'] ?? null,
            $config['hooks'] ?? []
        );
    }

    /**
     * Create the header identity resolver
     *
     * @param array<string, mixed>                                                               $config
     * @param string                                                                             $name
     *
     * @phpstan-param array{header?: string|null, hooks?: array<\Sprout\Support\ResolutionHook>} $config
     *
     * @return \Sprout\Http\Resolvers\HeaderIdentityResolver
     */
    protected function createHeaderResolver(array $config, string $name): HeaderIdentityResolver
    {
        return new HeaderIdentityResolver(
            $name,
            $config['header'] ?? null,
            $config['hooks'] ?? []
        );
    }

    /**
     * Create the cookie identity resolver
     *
     * @param array<string, mixed>                                                                                                    $config
     * @param string                                                                                                                  $name
     *
     * @phpstan-param array{cookie?: string|null, options?: array<string, mixed>|null, hooks?: array<\Sprout\Support\ResolutionHook>} $config
     *
     * @return \Sprout\Http\Resolvers\CookieIdentityResolver
     */
    protected function createCookieResolver(array $config, string $name): CookieIdentityResolver
    {
        return new CookieIdentityResolver(
            $name,
            $config['cookie'] ?? null,
            $config['options'] ?? [],
            $config['hooks'] ?? []
        );
    }

    /**
     * Create the session identity resolver
     *
     * @param array<string, mixed>                                                                $config
     * @param string                                                                              $name
     *
     * @phpstan-param array{session?: string|null, hooks?: array<\Sprout\Support\ResolutionHook>} $config
     *
     * @return \Sprout\Http\Resolvers\SessionIdentityResolver
     */
    protected function createSessionResolver(array $config, string $name): SessionIdentityResolver
    {

        return new SessionIdentityResolver(
            $name,
            $config['session'] ?? null
        );
    }
}
