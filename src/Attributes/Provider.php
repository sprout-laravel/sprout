<?php
declare(strict_types=1);

namespace Sprout\Attributes;

use Attribute;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\ContextualAttribute;
use Sprout\Contracts\TenantProvider;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Managers\TenantProviderManager;

/**
 * Provider Attribute
 *
 * This is a contextual attribute that allows for the auto-injection of a
 * tenant provider using its registered name, or the default.
 *
 * @see     https://laravel.com/docs/12.x/container#contextual-attributes
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
     * @param Provider  $attribute
     * @param Container $container
     *
     * @return \Sprout\Contracts\TenantProvider<*>
     *
     * @throws BindingResolutionException
     * @throws MisconfigurationException
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
