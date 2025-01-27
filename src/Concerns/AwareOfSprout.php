<?php
declare(strict_types=1);

namespace Sprout\Concerns;

use Sprout\Sprout;

trait AwareOfSprout
{
    /**
     * @var \Sprout\Sprout
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
