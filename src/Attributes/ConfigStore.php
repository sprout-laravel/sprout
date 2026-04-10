<?php
declare(strict_types=1);

namespace Sprout\Bud\Attributes;

use Attribute;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Sprout\Bud\Contracts\ConfigStore as ConfigStoreContract;
use Sprout\Bud\Managers\ConfigStoreManager;

/**
 * Config Store Attribute
 *
 * This is a contextual attribute that allows for the auto-injection of a
 * config store, either using its registered name or the default.
 *
 * @see     https://laravel.com/docs/12.x/container#contextual-attributes
 *
 * @package Core
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class ConfigStore implements ContextualAttribute
{
    public ?string $name;

    public function __construct(?string $name = null)
    {
        $this->name = $name;
    }

    /**
     * Resolve the config store using this attribute
     *
     * @param \Sprout\Bud\Attributes\ConfigStore   $attribute
     * @param \Illuminate\Contracts\Container\Container $container
     *
     * @return \Sprout\Bud\Contracts\ConfigStore
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Sprout\Core\Exceptions\MisconfigurationException
     */
    public function resolve(self $attribute, Container $container): ConfigStoreContract
    {
        return $container->make(ConfigStoreManager::class)->get($attribute->name);
    }
}
