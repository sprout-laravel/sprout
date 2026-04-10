<?php
declare(strict_types=1);

namespace Sprout\Core\Concerns;

use Sprout\Core\Sprout;

trait AwareOfSprout
{
    /**
     * @var \Sprout\Core\Sprout
     */
    private Sprout $sprout;

    public function setSprout(Sprout $sprout): static
    {
        $this->sprout = $sprout;

        return $this;
    }

    public function getSprout(): Sprout
    {
        return $this->sprout;
    }
}
