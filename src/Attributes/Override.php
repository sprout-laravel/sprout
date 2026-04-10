<?php
declare(strict_types=1);

namespace Sprout\Core\Attributes;

use Attribute;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Sprout\Core\Contracts\ServiceOverride;
use Sprout\Core\Managers\ServiceOverrideManager;

/**
 * Override Attribute
 *
 * This is a contextual attribute that allows for the auto-injection of a
 * service override using its registered name.
 *
 * @see     https://laravel.com/docs/12.x/container#contextual-attributes
 *
 * @package Core
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Override implements ContextualAttribute
{
    /**
     * The service override to inject
     *
     * @var string
     */
    public string $service;

    /**
     * Create a new instance
     *
     * @param string $service
     */
    public function __construct(string $service)
    {
        $this->service = $service;
    }

    /**
     * Resolve the tenancy using this attribute
     *
     * @param \Sprout\Core\Attributes\Override $attribute
     * @param \Illuminate\Container\Container  $container
     *
     * @return \Sprout\Core\Contracts\ServiceOverride|null
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function resolve(self $attribute, Container $container): ?ServiceOverride
    {
        /**
         * It's not nullable, it'll be an exception
         *
         * @noinspection NullPointerExceptionInspection
         */
        return $container->make(ServiceOverrideManager::class)->get($this->service);
    }
}
