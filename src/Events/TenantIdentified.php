<?php
declare(strict_types=1);

namespace Sprout\Events;

/**
 * Tenant Identified Event
 *
 * This is a child of {@see TenantFound} that is used to notify
 * the application and provide reactivity when a tenant is found using its
 * identifier, referred to as being "identified".
 *
 * @template TenantClass of \Sprout\Contracts\Tenant
 *
 * @extends TenantFound<TenantClass>
 *
 * @codeCoverageIgnore
 */
final readonly class TenantIdentified extends TenantFound
{
}
