<?php
declare(strict_types=1);

namespace Sprout\Core\Events;

/**
 * Tenant Identified Event
 *
 * This is a child of {@see \Sprout\Core\Events\TenantFound} that is used to notify
 * the application and provide reactivity when a tenant is found using its
 * identifier, referred to as being "identified".
 *
 * @template TenantClass of \Sprout\Core\Contracts\Tenant
 *
 * @extends \Sprout\Core\Events\TenantFound<TenantClass>
 *
 * @package Core
 *
 * @codeCoverageIgnore
 */
final readonly class TenantIdentified extends TenantFound
{

}
