<?php
declare(strict_types=1);

namespace Sprout\Attributes;

use Attribute;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Sprout\Contracts\TenantProvider;
use Sprout\Managers\TenantProviderManager;

/**
 * Provider Attribute
 *
 * This is a contextual attribute that allows for the auto-injection of a
 * tenant provider using its registered name, or the default.
 *
 * @see     https://laravel.com/docs/12.x/container#contextual-attributes
 *
 * @package Core
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Provider implements ContextualAttribute
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
     * @param \Sprout\Attributes\Provider     $attribute
     * @param \Illuminate\Container\Container $container
     *
     * @return \Sprout\Contracts\TenantProvider<*>
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function resolve(self $attribute, Container $container): TenantProvider
    {
        /**
         * It's not nullable, it'll be an exception
         *
         * @noinspection NullPointerExceptionInspection
         */
        return $container->make(TenantProviderManager::class)->get($this->name);
    }
}
