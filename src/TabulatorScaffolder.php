<?php

namespace LeKoala\Tabulator;

use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormScaffolder;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;

class TabulatorScaffolder extends FormScaffolder
{
    public static function scaffoldFormFields(DataObject $obj, array $_params = []): FieldList
    {
        $params = array_merge(
            [
                'tabbed' => false,
                'includeRelations' => false,
                'restrictFields' => false,
                'fieldClasses' => false,
                'ajaxSafe' => false
            ],
            $_params
        );

        $fs = new self($obj);
        $fs->tabbed = $params['tabbed'];
        $fs->includeRelations = $params['includeRelations'];
        $fs->restrictFields = $params['restrictFields'];
        $fs->fieldClasses = $params['fieldClasses'];
        $fs->ajaxSafe = $params['ajaxSafe'];

        return $fs->getFieldList();
    }

    /**
     * Gets the form fields as defined through the metadata
     * on {@link $obj} and the custom parameters passed to FormScaffolder.
     * Depending on those parameters, the fields can be used in ajax-context,
     * contain {@link TabSet}s etc.
     */
    public function getFieldList(): FieldList
    {
        $fields = new FieldList();

        // tabbed or untabbed
        if ($this->tabbed) {
            $fields->push(new TabSet("Root", $mainTab = new Tab("Main")));
            $mainTab->setTitle(_t(__CLASS__ . '.TABMAIN', 'Main'));
        }

        // Add logical fields directly specified in db config
        foreach ($this->obj->config()->get('db') as $fieldName => $fieldType) {
            // Skip restricted fields
            if ($this->restrictFields && !in_array($fieldName, $this->restrictFields)) {
                continue;
            }

            // @todo Pass localized title
            if ($this->fieldClasses && isset($this->fieldClasses[$fieldName])) {
                $fieldClass = $this->fieldClasses[$fieldName];
                $fieldObject = new $fieldClass($fieldName);
            } else {
                $fieldObject = $this
                    ->obj
                    ->dbObject($fieldName)
                    ->scaffoldFormField(null, $this->getParamsArray());
            }
            // Allow fields to opt-out of scaffolding
            if (!$fieldObject) {
                continue;
            }
            $fieldObject->setTitle($this->obj->fieldLabel($fieldName));
            if ($this->tabbed) {
                $fields->addFieldToTab("Root.Main", $fieldObject);
            } else {
                $fields->push($fieldObject);
            }
        }

        // add has_one relation fields
        if ($this->obj->hasOne()) {
            foreach ($this->obj->hasOne() as $relationship => $component) {
                if ($this->restrictFields && !in_array($relationship, $this->restrictFields)) {
                    continue;
                }
                $fieldName = $component === DataObject::class
                    ? $relationship // Polymorphic has_one field is composite, so don't refer to ID subfield
                    : "{$relationship}ID";
                if ($this->fieldClasses && isset($this->fieldClasses[$fieldName])) {
                    $fieldClass = $this->fieldClasses[$fieldName];
                    $hasOneField = new $fieldClass($fieldName);
                } else {
                    $hasOneField = $this->obj->dbObject($fieldName)->scaffoldFormField(null, $this->getParamsArray());
                }
                if (empty($hasOneField)) {
                    continue; // Allow fields to opt out of scaffolding
                }
                $hasOneField->setTitle($this->obj->fieldLabel($relationship));
                if ($this->tabbed) {
                    $fields->addFieldToTab("Root.Main", $hasOneField);
                } else {
                    $fields->push($hasOneField);
                }
            }
        }

        // only add relational fields if an ID is present
        if ($this->obj->ID) {
            // add has_many relation fields
            $includeHasMany = $this->obj->hasMany() && ($this->includeRelations === true || isset($this->includeRelations['has_many']));

            if ($includeHasMany) {
                foreach ($this->obj->hasMany() as $relationship => $component) {
                    static::addDefaultRelationshipFields(
                        $fields,
                        $relationship,
                        (isset($this->fieldClasses[$relationship]))
                            ? $this->fieldClasses[$relationship] : null,
                        $this->tabbed,
                        $this->obj
                    );
                }
            }

            $includeManyMany = $this->obj->manyMany() && ($this->includeRelations === true || isset($this->includeRelations['many_many']));
            if ($includeManyMany) {
                foreach ($this->obj->manyMany() as $relationship => $component) {
                    static::addDefaultRelationshipFields(
                        $fields,
                        $relationship,
                        (isset($this->fieldClasses[$relationship]))
                            ? $this->fieldClasses[$relationship] : null,
                        $this->tabbed,
                        $this->obj
                    );
                }
            }
        }

        return $fields;
    }

    /**
     * Adds the default relation fields for the relationship provided.
     *
     * @param FieldList $fields Reference to the @FieldList to add fields to.
     * @param string $relationship The relationship identifier.
     * @param mixed $overrideFieldClass Specify the field class to use here or leave as null to use default.
     * @param bool $tabbed Whether this relationship has it's own tab or not.
     * @param DataObject $dataObject The @DataObject that has the relation.
     */
    public static function addDefaultRelationshipFields(
        FieldList &$fields,
        $relationship,
        $overrideFieldClass,
        $tabbed,
        DataObject $dataObject
    ) {
        if ($tabbed) {
            $fields->findOrMakeTab(
                "Root.$relationship",
                $dataObject->fieldLabel($relationship)
            );
        }

        $fieldClass = $overrideFieldClass ?: TabulatorGrid::class;

        /** @var TabulatorGrid $grid */
        $grid = Injector::inst()->create(
            $fieldClass,
            $relationship,
            $dataObject->fieldLabel($relationship),
            $dataObject->$relationship(),
        );
        $grid->setLazyInit(true);
        if ($tabbed) {
            $fields->addFieldToTab("Root.$relationship", $grid);
        } else {
            $fields->push($grid);
        }
    }
}
