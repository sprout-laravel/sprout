<?php
declare(strict_types=1);

namespace Sprout\Attributes;

use Attribute;

/**
 * Tenant Relation Attribute
 *
 * This attribute marks a relation method within an Eloquent model as
 * relating to the tenant.
 *
 * This is primarily used in the tenant child/descendant functionality that
 * comes with Sprout.
 *
 * @package Database\Eloquent
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class TenantRelation
{
}
