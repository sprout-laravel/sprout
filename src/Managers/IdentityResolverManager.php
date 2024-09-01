<?php
declare(strict_types=1);

namespace Sprout\Managers;

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
        return 'tenanted.resolvers.' . $name;
    }
}
