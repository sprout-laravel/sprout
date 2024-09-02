<?php
declare(strict_types=1);

namespace Sprout\Support;

use Sprout\Contracts\IdentityResolver;

abstract class BaseIdentityResolver implements IdentityResolver
{
    /**
     * @var string
     */
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get the registered name of the resolver
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}
