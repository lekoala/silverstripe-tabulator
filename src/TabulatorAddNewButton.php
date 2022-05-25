<?php

namespace LeKoala\Tabulator;

use SilverStripe\Control\Controller;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\RelationList;
use SilverStripe\View\ArrayData;

/**
 * This component provides a button for opening the add new form
 */
class TabulatorAddNewButton extends AbstractTabulatorTool
{
    public function forTemplate()
    {
        $grid = $this->tabulatorGrid;
        $singleton = singleton($grid->getModelClass());
        $context = [];
        if ($grid->getList() instanceof RelationList) {
            $record = $grid->getForm()->getRecord();
            if ($record && $record instanceof DataObject) {
                $context['Parent'] = $record;
            }
        }

        if (!$singleton->canCreate(null, $context)) {
            return false;
        }

        if (!$this->buttonName) {
            // provide a default button name, can be changed by calling {@link setButtonName()} on this component
            $objectName = $singleton->i18n_singular_name();
            $this->buttonName = _t('SilverStripe\\Forms\\GridField\\GridField.Add', 'Add {name}', ['name' => $objectName]);
        }

        $data = new ArrayData([
            'NewLink' => Controller::join_links($grid->Link('item'), 'new'),
            'ButtonName' => $this->buttonName,
        ]);
        return $this->renderWith($this->getViewerTemplates(), $data);
    }
}
