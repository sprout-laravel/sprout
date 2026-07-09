<?php
declare(strict_types=1);

namespace Sprout\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Sprout\Support\PlaceholderHelper;
use Sprout\Tests\Unit\UnitTestCase;

class PlaceholderHelperTest extends UnitTestCase
{
    #[Test]
    public function replacesEveryPlaceholderCasing(): void
    {
        // The lower, ucfirst and upper forms of a placeholder each receive the
        // correspondingly-cased value.
        $this->assertSame(
            'acme/Acme/ACME',
            PlaceholderHelper::replace('{tenant}/{Tenant}/{TENANT}', ['tenant' => 'acme']),
        );
    }

    #[Test]
    public function normalisesThePlaceholderKeyToLowercase(): void
    {
        // A mixed-case key is lower-cased, so it still matches the lower-case
        // placeholder in the pattern.
        $this->assertSame(
            'acme',
            PlaceholderHelper::replace('{tenant}', ['Tenant' => 'acme']),
        );
    }

    #[Test]
    public function replacesParameterHyphensWithUnderscores(): void
    {
        // replaceForParameter() additionally swaps '-' for '_'.
        $this->assertSame(
            'my_tenant_value',
            PlaceholderHelper::replaceForParameter('{tenant}', ['tenant' => 'my-tenant-value']),
        );
    }
}
