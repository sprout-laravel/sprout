<?php
declare(strict_types=1);

namespace Sprout\Attributes;

use Attribute;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Sprout\Bud;

/**
 * Config Store Attribute
 *
 * This is a contextual attribute that allows for the auto-injection of
 * tenant config, using the name of the service and the name of the config.
 *
 * @see     https://laravel.com/docs/12.x/container#contextual-attributes
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class TenantConfig implements ContextualAttribute
{
    public string $name;

    private string $service;

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
     * @param TenantConfig $attribute
     * @param Container    $container
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
            store: $attribute->store,
        );
    }
}
