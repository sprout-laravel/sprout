<?php
declare(strict_types=1);

namespace Sprout\Core\Attributes;

use Attribute;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Sprout\Core\Contracts\Tenancy;
use Sprout\Core\Sprout;

/**
 * Current Tenancy Attribute
 *
 * This is a contextual attribute that allows for the auto-injection of the
 * current tenancy.
 *
 * @link     https://laravel.com/docs/12.x/container#contextual-attributes
 *
 * @package  Core
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class CurrentTenancy implements ContextualAttribute
{
    /**
     * Resolve the tenancy using this attribute
     *
     * @param \Sprout\Core\Attributes\CurrentTenancy $attribute
     * @param \Illuminate\Container\Container        $container
     *
     * @return \Sprout\Core\Contracts\Tenancy<*>|null
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function resolve(self $attribute, Container $container): ?Tenancy
    {
        return $container->make(Sprout::class)->getCurrentTenancy();
    }
}
