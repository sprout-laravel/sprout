<?php
declare(strict_types=1);

namespace Sprout\Events;

/**
 * @template TenantClass of \Sprout\Contracts\Tenant
 *
 * @extends \Sprout\Events\TenantFound<TenantClass>
 */
final readonly class TenantIdentified extends TenantFound
{

}
