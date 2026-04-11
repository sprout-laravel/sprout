<?php
declare(strict_types=1);

namespace Sprout\Attributes;

use Attribute;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\ContextualAttribute;
use Sprout\Contracts\Tenancy as TenancyContract;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Managers\TenancyManager;

/**
 * Tenancy Attribute
 *
 * This is a contextual attribute that allows for the auto-injection of the
 * tenancy, either using its registered name or the default.
 *
 * @see     https://laravel.com/docs/12.x/container#contextual-attributes
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Tenancy implements ContextualAttribute
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
     * @param Tenancy   $attribute
     * @param Container $container
     *
     * @return \Sprout\Contracts\Tenancy<*>
     *
     * @throws BindingResolutionException
     * @throws MisconfigurationException
     */
    public function resolve(self $attribute, Container $container): TenancyContract
    {
        /**
         * It's not nullable, it'll be an exception
         *
         * @noinspection NullPointerExceptionInspection
         */
        return $container->make(TenancyManager::class)->get($this->name);
    }
}
