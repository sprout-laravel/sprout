<?php
declare(strict_types=1);

namespace Sprout\Managers;

use InvalidArgumentException;
use Sprout\Http\Resolvers\PathIdentityResolver;
use Sprout\Http\Resolvers\SubdomainIdentityResolver;
use Sprout\Support\BaseFactory;

/**
 * @extends \Sprout\Support\BaseFactory<\Sprout\Contracts\IdentityResolver>
 */
final class IdentityResolverManager extends BaseFactory
{
    /**
     * Get the name used by this factory
     *
     * @return string
     */
    protected function getFactoryName(): string
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
    protected function getConfigKey(string $name): string
    {
        return 'multitenancy.resolvers.' . $name;
    }

    /**
     * Create the subdomain identity resolver
     *
     * @param array<string, mixed>                                                           $config
     * @param string                                                                         $name
     *
     * @phpstan-param array{domain?: string, pattern?: string|null, parameter?: string|null} $config
     *
     * @return \Sprout\Http\Resolvers\SubdomainIdentityResolver
     */
    protected function createSubdomainResolver(array $config, string $name): SubdomainIdentityResolver
    {
        if (! isset($config['domain'])) {
            throw new InvalidArgumentException(
                'No domain provided for resolver [' . $name . ']'
            );
        }

        return new SubdomainIdentityResolver(
            $name,
            $config['domain'],
            $config['pattern'] ?? null,
            $config['parameter'] ?? null
        );
    }

    /**
     * Create the path identity resolver
     *
     * @param array<string, mixed>                                                              $config
     * @param string                                                                            $name
     *
     * @phpstan-param array{segment?: int|null, pattern?: string|null, parameter?: string|null} $config
     *
     * @return \Sprout\Http\Resolvers\PathIdentityResolver
     */
    protected function createPathResolver(array $config, string $name): PathIdentityResolver
    {
        $segment = $config['segment'] ?? 1;

        if ($segment < 1) {
            throw new InvalidArgumentException(
                'Invalid path segment [' . $segment . '], path segments should be 1 indexed'
            );
        }

        return new PathIdentityResolver(
            $name,
            $segment,
            $config['pattern'] ?? null,
            $config['parameter'] ?? null
        );
    }
}
