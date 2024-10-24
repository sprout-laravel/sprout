<?php
declare(strict_types=1);

namespace Sprout\Events;

/**
 * Tenant Identified Event
 *
 * This is a child of {@see \Sprout\Events\TenantFound} that is used to notify
 * the application and provide reactivity when a tenant is found using its
 * key, referred to as being "loaded".
 *
 * @template TenantClass of \Sprout\Contracts\Tenant
 *
 * @extends \Sprout\Events\TenantFound<TenantClass>
 *
 * @package Core
 */
final readonly class TenantLoaded extends TenantFound
{

}
