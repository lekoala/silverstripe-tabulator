<?php

namespace LeKoala\Tabulator;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FormField;
use SilverStripe\Control\Controller;

/**
 * Inspired by https://github.com/silvershop/silverstripe-hasonefield
 * but using TabulatorGrid under the hood
 */
class HasOneTabulatorField extends TabulatorGrid
{
    /**
     * The related object to the parent
     */
    protected DataObject $record;

    /**
     * The current parent of the relationship (the base object we are editing)
     */
    protected DataObject $parent;

    /**
     * The name of the relation this field is managing
     *
     * @var string
     */
    protected string $relation;

    /**
     * HasOneButtonField constructor.
     * @param \SilverStripe\ORM\DataObject $parent
     * @param string $relationName
     * @param string|null $fieldName
     * @param string|null $title
     */
    public function __construct(DataObject $parent, string $relationName, string $fieldName = null, string $title = null)
    {
        $record = $parent->{$relationName}();
        $this->setRecord($record);
        $this->parent = $parent;
        $this->relation = $relationName;

        $list = HasOneButtonRelationList::create($parent, $this->record, $relationName);

        parent::__construct($fieldName ?: $relationName, $title, $list);
        $this->setModelClass($record->ClassName);
    }

    public function setValue($value, $data = null)
    {
        if ($value instanceof DataObject) {
            $value = HasOneButtonRelationList::create($this->parent, $value, $this->relation);
        }
        return parent::setValue($value, $data);
    }

    /**
     */
    public function getRecord(): DataObject
    {
        return $this->record;
    }

    /**
     */
    public function setRecord(DataObject $record = null)
    {
        $this->record = $record ?: singleton(get_class($this->record));
    }

    /**
     * Get the current parent
     */
    public function getParent(): DataObject
    {
        return $this->parent;
    }

    /**
     * Set the current parent
     *
     * @param DataObject $parent parent of the relationship
     */
    public function setParent(DataObject $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Get the name of the relation this field is managing
     */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * Set the name of the relation this field is managing
     *
     * @param string $relation The relation name
     */
    public function setRelation(string $relation): self
    {
        $this->relation = $relation;
        return $this;
    }

    public function FieldHolder($properties = [])
    {
        return FormField::FieldHolder($properties);
    }

    public function Field($properties = [])
    {
        return FormField::Field($properties);
    }

    public function getEditLink(): string
    {
        return Controller::join_links($this->Link('item'), $this->record->ID);
    }
}
