<?php
declare(strict_types=1);

namespace Sprout\Bud\Attributes;

use Attribute;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Sprout\Bud\Bud;

/**
 * Config Store Attribute
 *
 * This is a contextual attribute that allows for the auto-injection of
 * tenant config, using the name of the service and the name of the config.
 *
 * @see     https://laravel.com/docs/12.x/container#contextual-attributes
 *
 * @package Core
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class TenantConfig implements ContextualAttribute
{
    private string $service;

    public string $name;

    private ?string $store;

    public function __construct(string $service, string $name, ?string $store = null)
    {
        $this->name    = $name;
        $this->service = $service;
        $this->store   = $store;
    }

    /**
     * Resolve the config store using this attribute
     *
     * @param \Sprout\Bud\Attributes\TenantConfig       $attribute
     * @param \Illuminate\Contracts\Container\Container $container
     *
     * @return array<string, mixed>|null
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Exceptions\MisconfigurationException
     */
    public function resolve(self $attribute, Container $container): ?array
    {
        return $container->make(Bud::class)->config(
            $attribute->service,
            $attribute->name,
            store: $attribute->store
        );
    }
}
