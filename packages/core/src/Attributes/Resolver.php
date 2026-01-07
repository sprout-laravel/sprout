<?php
declare(strict_types=1);

namespace Sprout\Attributes;

use Attribute;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Sprout\Contracts\IdentityResolver;
use Sprout\Managers\IdentityResolverManager;

/**
 * Resolver Attribute
 *
 * This is a contextual attribute that allows for the auto-injection of an
 * identity resolver using its registered name, or the default.
 *
 * @see     https://laravel.com/docs/12.x/container#contextual-attributes
 *
 * @package Core
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Resolver implements ContextualAttribute
{
    /**
     * @var string|null
     */
    public ?string $name;

    /**
     * Create a new instance
     *
     * @param string|null $name
     */
    public function __construct(?string $name = null)
    {
        $this->name = $name;
    }

    /**
     * Resolve the tenancy using this attribute
     *
     * @param \Sprout\Attributes\Resolver     $attribute
     * @param \Illuminate\Container\Container $container
     *
     * @return \Sprout\Contracts\IdentityResolver
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function resolve(self $attribute, Container $container): IdentityResolver
    {
        /**
         * It's not nullable, it'll be an exception
         *
         * @noinspection NullPointerExceptionInspection
         */
        return $container->make(IdentityResolverManager::class)->get($this->name);
    }
}
