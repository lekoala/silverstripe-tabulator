<?php

namespace LeKoala\Tabulator;

use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;

/**
 * Class HasOneButtonRelationList
 * @link https://github.com/silvershop/silverstripe-hasonefield/blob/main/src/HasOneButtonRelationList.php
 */
class HasOneButtonRelationList extends ArrayList
{
    /**
     * @var DataObject
     */
    protected $record;

    /**
     * @var string
     */
    protected $relationName;

    /**
     * @var DataObject
     */
    protected $parent;

    /**
     * HasOneButtonRelationList constructor.
     * @param DataObject $parent
     * @param DataObject $record
     * @param string $relationName
     */
    public function __construct(DataObject $parent, DataObject $record, $relationName)
    {
        $this->record = $record;
        $this->relationName = $relationName;
        $this->parent = $parent;

        parent::__construct([$record]);
    }

    public function add($item)
    {
        $parent = $this->parent;
        // Get the relationship type (has_one or belongs_to)
        $relationType = $parent->getRelationType($this->relationName);
        switch ($relationType) {
            case 'belongs_to':
                // If belongs_to, retrieve and write to the has_one side of the relationship
                $parent->{$this->relationName} = $item;
                $hasOneRecord = $parent->getComponent($this->relationName);
                $hasOneRecord->write();
                break;
            default:
                // Otherwise assume has_one, and write to this record
                $parent->{$this->relationName} = $item;
                $parent->write();
                break;
        }

        $this->items = [$item];
    }

    public function remove($item)
    {
        $parent = $this->parent;
        $relationName = $this->relationName;
        // Get the relationship type (has_one or belongs_to)
        $relationType = $parent->getRelationType($relationName);
        switch ($relationType) {
            case 'belongs_to':
                // If belongs_to, retrieve and write to the has_one side of the relationship
                $hasOneRecord = $parent->getComponent($this->relationName);
                /** @var DataObject $parentClass */
                $parentClass = $parent->getClassName();

                $schema = $parentClass::getSchema();
                $polymorphic = false;
                $hasOneFieldName = $schema->getRemoteJoinField(
                    $parentClass,
                    $relationName,
                    $relationType,
                    $polymorphic
                );

                $hasOneRecord->{$hasOneFieldName} = null;
                $hasOneRecord->write();
                break;
            default:
                // Otherwise assume has_one, and write to this record
                $parent->{$relationName} = null;
                $parent->write();
                break;
        }

        $this->items = [];
    }
}
