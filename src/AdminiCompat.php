<?php

namespace LeKoala\Tabulator;

use SilverStripe\View\HTML;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Control\Controller;
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
                        ->addExtraClass('btn-outline-success')
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

            $actions->push($this->getRightGroupField($itemRequest));
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
                    "ms-auto btn btn-default", // CSS classes
                    $oneLevelUp->Link, // url
                    _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.CancelBtn', 'Cancel') // label
                );
                $actions->insertAfter('MajorActions', new LiteralField('cancelbutton', $text));
            }
        }

        return $actions;
    }

    /**
     * @return CompositeField Returns the right aligned toolbar group field along with its FormAction's
     */
    protected function getRightGroupField(TabulatorGrid_ItemRequest $itemRequest)
    {
        $rightGroup = CompositeField::create()->setName('RightGroup');
        $rightGroup->addExtraClass('ms-auto');
        $rightGroup->setFieldHolderTemplate(get_class($rightGroup) . '_holder_buttongroup');

        $previousAndNextGroup = CompositeField::create()->setName('PreviousAndNextGroup');
        $previousAndNextGroup->setFieldHolderTemplate(CompositeField::class . '_holder_buttongroup');

        $grid = $itemRequest->getTabulatorGrid();
        $record = $itemRequest->getRecord();
        $pagination = $grid->getOption("pagination");
        if ($pagination) {
            $previousIsDisabled = !$itemRequest->getPreviousRecordID();
            $nextIsDisabled = !$itemRequest->getNextRecordID();

            $previousAndNextGroup->push(
                LiteralField::create(
                    'previous-record',
                    HTML::createTag($previousIsDisabled ? 'span' : 'a', [
                        'href' => $previousIsDisabled ? '#' : $itemRequest->getEditLink($itemRequest->getPreviousRecordID()),
                        'title' => _t(__CLASS__ . '.PREVIOUS', 'Go to previous record'),
                        'aria-label' => _t(__CLASS__ . '.PREVIOUS', 'Go to previous record'),
                        'class' => 'btn btn-light action--previous discard-confirmation'
                            . ($previousIsDisabled ? ' disabled' : ''),
                    ], '<l-i name="navigate_before"></l-i>')
                )
            );

            $previousAndNextGroup->push(
                LiteralField::create(
                    'next-record',
                    HTML::createTag($nextIsDisabled ? 'span' : 'a', [
                        'href' => $nextIsDisabled ? '#' : $itemRequest->getEditLink($itemRequest->getNextRecordID()),
                        'title' => _t(__CLASS__ . '.NEXT', 'Go to next record'),
                        'aria-label' => _t(__CLASS__ . '.NEXT', 'Go to next record'),
                        'class' => 'btn btn-light action--next discard-confirmation'
                            . ($nextIsDisabled ? ' disabled' : ''),
                    ], '<l-i name="navigate_next"></l-i>')
                )
            );
        }

        $rightGroup->push($previousAndNextGroup);

        if ($record->canCreate()) {
            $rightGroup->push(
                LiteralField::create(
                    'new-record',
                    HTML::createTag('a', [
                        'href' => Controller::join_links($grid->Link('item'), 'new'),
                        'title' => _t(__CLASS__ . '.NEW', 'Add new record'),
                        'aria-label' => _t(__CLASS__ . '.NEW', 'Add new record'),
                        'class' => 'btn btn-success action--new discard-confirmation',
                    ], '<l-i name="add"></l-i>')
                )
            );
        }

        return $rightGroup;
    }

    /**
     * Add Save&Close if not using cms-actions
     *
     * @param FieldList $actions
     * @param DataObject $record
     * @return void
     */
    public function addSaveAndClose(FieldList $actions, DataObject $record)
    {
        if (!$record->canEdit()) {
            return;
        }
        if (!$record->ID && !$record->canCreate()) {
            return;
        }

        $MajorActions = $actions->fieldByName('MajorActions');

        // If it doesn't exist, push to default group
        if (!$MajorActions) {
            $MajorActions = $actions;
        }

        if ($record->ID) {
            $label = _t(__CLASS__ . '.SAVEANDCLOSE', 'Save and Close');
        } else {
            $label = _t(__CLASS__ . '.CREATEANDCLOSE', 'Create and Close');
        }

        $saveAndClose = new FormAction('doSaveAndClose', $label);
        $saveAndClose->addExtraClass('btn btn-outline-primary');
        if ($record->ID) {
            $saveAndClose->setIcon("done");
        } else {
            $saveAndClose->setIcon("add");
        }

        $saveAndClose->setUseButtonTag(true);
        $MajorActions->push($saveAndClose);
    }
}
