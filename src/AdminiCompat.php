<?php

namespace LeKoala\Tabulator;

use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\CompositeField;

/**
 * Compat layer with admini module
 * Maybe migrate this to admini module at some point
 */
class AdminiCompat implements CompatLayerInterface
{
    public function adjustItemEditForm(TabulatorGrid_ItemRequest $itemRequest, Form $form)
    {
        $form->setTemplate([
            'type' => 'Includes',
            'LeKoala\\Admini\\LeftAndMain_EditForm',
        ]);
        if ($form->Fields()->hasTabSet()) {
            $form->Fields()->findOrMakeTab('Root')->setTemplate('SilverStripe\\Forms\\CMSTabSet');
        }
        $form->Backlink = $itemRequest->getBackLink();
    }

    public function getFormActions(TabulatorGrid_ItemRequest $itemRequest): FieldList
    {
        $actions = FieldList::create();
        $majorActions = CompositeField::create()->setName('MajorActions');
        $majorActions->setFieldHolderTemplate(get_class($majorActions) . '_holder_buttongroup');
        $actions->push($majorActions);

        $record = $itemRequest->getRecord();

        if ($record->ID !== 0) { // existing record
            if ($record->canEdit()) {
                $majorActions->push(
                    FormAction::create('doSave', _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Save', 'Save'))
                        ->setIcon('done')
                        ->addExtraClass('btn-success')
                        ->setUseButtonTag(true)
                );
            }

            if ($record->canDelete()) {
                $actions->insertAfter(
                    'MajorActions',
                    FormAction::create('doDelete', _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Delete', 'Delete'))
                        ->setUseButtonTag(true)
                        ->setIcon('delete')
                        ->addExtraClass('btn-danger')
                );
            }
        } else { // adding new record
            //Change the Save label to 'Create'
            $majorActions->push(FormAction::create('doSave', _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Create', 'Create'))
                ->setUseButtonTag(true)
                ->setIcon('add')
                ->addExtraClass('btn-success'));

            // Add a Cancel link which is a button-like link and link back to one level up.
            $crumbs = $itemRequest->Breadcrumbs();
            if ($crumbs && $crumbs->count() >= 2) {
                $oneLevelUp = $crumbs->offsetGet($crumbs->count() - 2);
                $text = sprintf(
                    "<a class=\"%s\" href=\"%s\">%s</a>",
                    "crumb btn btn-secondary cms-panel-link", // CSS classes
                    $oneLevelUp->Link, // url
                    _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.CancelBtn', 'Cancel') // label
                );
                $actions->insertAfter('MajorActions', new LiteralField('cancelbutton', $text));
            }
        }

        return $actions;
    }
}
