<?php
declare(strict_types=1);

namespace Sprout\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Sprout\Contracts\TenantRelationHandler;

/**
 * @template ParentModel of \Illuminate\Database\Eloquent\Model
 * @template ChildModel of \Illuminate\Database\Eloquent\Model&\Sprout\Contracts\Tenant
 * @template RelationClass of \Illuminate\Database\Eloquent\Relations\Relation
 *
 * @implements \Sprout\Contracts\TenantRelationHandler<ParentModel, ChildModel, RelationClass>
 */
abstract class BaseTenantRelationHandler implements TenantRelationHandler
{
    /**
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     *
     * @phpstan-var class-string<ParentModel>
     */
    private string $parentModel;

    /**
     * @var string
     */
    private string $relationName;

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $parentModel
     * @param string                                            $relationName
     *
     * @phpstan-param class-string<ParentModel>                 $parentModel
     */
    public function __construct(string $parentModel, string $relationName)
    {
        $this->parentModel  = $parentModel;
        $this->relationName = $relationName;
    }

    /**
     * Get the model class that belongs to the tenant
     *
     * @return class-string<ParentModel>
     */
    public function getParentModel(): string
    {
        return $this->parentModel;
    }

    /**
     * Get the name of the tenant relationship
     *
     * @return string
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }

    /**
     * Get the tenant relationship
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation<ChildModel>
     *
     * @phpstan-param ParentModel                 $model
     *
     * @phpstan-return RelationClass
     */
    public function getRelation(Model $model): Relation
    {
        return $model->{$this->getRelationName()}();
    }
}
