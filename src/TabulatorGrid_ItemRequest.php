<?php

namespace LeKoala\Tabulator;

use Exception;
use SilverStripe\View\HTML;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\SSViewer;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\ORM\RelationList;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\CompositeField;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Control\RequestHandler;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;

class TabulatorGrid_ItemRequest extends RequestHandler
{

    private static $allowed_actions = [
        'edit',
        'view',
        'customAction',
        'ItemEditForm',
    ];

    protected TabulatorGrid $tabulatorGrid;

    /**
     * @var DataObject
     */
    protected $record;

    /**
     * This represents the current parent RequestHandler (which does not necessarily need to be a Controller).
     * It allows us to traverse the RequestHandler chain upwards to reach the Controller stack.
     *
     * @var RequestHandler
     */
    protected $popupController;

    protected string $template = '';

    private static $url_handlers = [
        'customAction/$CustomAction' => 'customAction',
        '$Action!' => '$Action',
        '' => 'edit',
    ];

    /**
     *
     * @param TabulatorGrid $tabulatorGrid
     * @param DataObject $record
     * @param RequestHandler $requestHandler
     */
    public function __construct($tabulatorGrid, $record, $requestHandler)
    {
        $this->tabulatorGrid = $tabulatorGrid;
        $this->record = $record;
        $this->popupController = $requestHandler;
        parent::__construct();
    }

    public function Link($action = null)
    {
        return Controller::join_links(
            $this->tabulatorGrid->Link('item'),
            $this->record->ID ? $this->record->ID : 'new',
            $action
        );
    }

    public function AbsoluteLink($action = null)
    {
        return Director::absoluteURL($this->Link($action));
    }

    public function index(HTTPRequest $request)
    {
        $controller = $this->getToplevelController();
        return $controller->redirect($this->Link('edit'));
    }

    protected function returnWithinContext(HTTPRequest $request, RequestHandler $controller, Form $form)
    {
        $data = $this->customise([
            'Backlink' => $controller->hasMethod('Backlink') ? $controller->Backlink() : $controller->Link(),
            'ItemEditForm' => $form,
        ]);

        $return = $data->renderWith('LeKoala\\Tabulator\\TabulatorGrid_ItemEditForm');
        if ($request->isAjax()) {
            return $return;
        }
        // If not requested by ajax, we need to render it within the controller context+template
        return $controller->customise([
            'Content' => $return,
        ]);
    }

    /**
     * This is responsible to display an edit form, like GridFieldDetailForm, but much simpler
     *
     * @return mixed
     */
    public function edit(HTTPRequest $request)
    {
        if (!$this->record->canEdit()) {
            return $this->httpError(403, _t(
                __CLASS__ . '.EditPermissionsFailure',
                'It seems you don\'t have the necessary permissions to edit "{ObjectTitle}"',
                ['ObjectTitle' => $this->record->singular_name()]
            ));
        }
        $controller = $this->getToplevelController();

        $form = $this->ItemEditForm();

        return $this->returnWithinContext($request, $controller, $form);
    }

    /**
     * @return mixed
     */
    public function view(HTTPRequest $request)
    {
        if (!$this->record->canView()) {
            return $this->httpError(403, _t(
                __CLASS__ . '.ViewPermissionsFailure',
                'It seems you don\'t have the necessary permissions to view "{ObjectTitle}"',
                ['ObjectTitle' => $this->record->singular_name()]
            ));
        }

        $controller = $this->getToplevelController();

        $form = $this->ItemEditForm();
        $form->makeReadonly();

        return $this->returnWithinContext($request, $controller, $form);
    }

    /**
     * This is responsible to forward actions to the model if necessary
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function customAction(HTTPRequest $request)
    {
        // This gets populated thanks to our updated URL handler
        $params = $request->params();
        $customAction = $params['CustomAction'] ?? null;
        $ID = $params['ID'] ?? 0;

        $dataClass = $this->tabulatorGrid->getModelClass();
        $record = DataObject::get_by_id($dataClass, $ID);
        $rowActions = $record->tabulatorRowActions();
        $validActions = array_keys($rowActions);
        if (!$customAction || !in_array($customAction, $validActions)) {
            return $this->httpError(403, _t(
                __CLASS__ . '.CustomActionPermissionsFailure',
                'It seems you don\'t have the necessary permissions to {ActionName} "{ObjectTitle}"',
                ['ActionName' => $customAction, 'ObjectTitle' => $this->record->singular_name()]
            ));
        }

        $clickedAction = $rowActions[$customAction];

        $error = false;
        try {
            $result = $record->$customAction();
        } catch (Exception $e) {
            $error = true;
            $result = $e->getMessage();
        }

        // Maybe it's a custom redirect or a file ?
        if ($result && $result instanceof HTTPResponse) {
            return $result;
        }

        // Show message on controller or in form
        $controller = $this->getToplevelController();
        if (Director::is_ajax()) {
            $controller = $this->getToplevelController();
            $controller->getResponse()->addHeader('X-Status', rawurlencode($result));
            if (!empty($clickedAction['refresh'])) {
                $controller->getResponse()->addHeader('X-Reload', "true");
            }
            // 4xx status makes a red box
            if ($error) {
                $controller->getResponse()->setStatusCode(400);
            }
        } else {
            $target = $this->ItemEditForm();
            if ($controller->hasMethod('sessionMessage')) {
                $target = $controller;
            }
            if ($target) {
                $target->sessionMessage($result, $error ? "bad" : "good");
            }
        }

        $url = $this->getBackURL()
            ?: $this->getReturnReferer()
            ?: $this->AbsoluteLink();

        return $controller->redirect($url);
    }

    /**
     * Builds an item edit form
     *
     * @return Form|HTTPResponse
     */
    public function ItemEditForm()
    {
        $list = $this->tabulatorGrid->getDataList();
        $controller = $this->getToplevelController();

        try {
            $record = $this->getRecord();
        } catch (Exception $e) {
            $url = $controller->getRequest()->getURL();
            $noActionURL = $controller->removeAction($url);
            //clear the existing redirect
            $controller->getResponse()->removeHeader('Location');
            return $controller->redirect($noActionURL, 302);
        }

        // If we are creating a new record in a has-many list, then
        // pre-populate the record's foreign key.
        if ($list instanceof HasManyList && !$this->record->isInDB()) {
            $key = $list->getForeignKey();
            $id = $list->getForeignID();
            $record->$key = $id;
        }

        if (!$record->canView()) {
            return $controller->httpError(403, _t(
                __CLASS__ . '.ViewPermissionsFailure',
                'It seems you don\'t have the necessary permissions to view "{ObjectTitle}"',
                ['ObjectTitle' => $this->record->singular_name()]
            ));
        }

        if ($record->hasMethod("tabulatorCMSFields")) {
            $fields = $record->tabulatorCMSFields();
        } else {
            $fields = $record->getCMSFields();
        }

        // If we are creating a new record in a has-many list, then
        // Disable the form field as it has no effect.
        if ($list instanceof HasManyList && !$this->record->isInDB()) {
            $key = $list->getForeignKey();

            if ($field = $fields->dataFieldByName($key)) {
                $fields->makeFieldReadonly($field);
            }
        }

        $actions = $this->getFormActions();
        $validator = null;

        $form = new Form(
            $this,
            'ItemEditForm',
            $fields,
            $actions,
            $validator
        );

        $form->loadDataFrom($record, $record->ID == 0 ? Form::MERGE_IGNORE_FALSEISH : Form::MERGE_DEFAULT);

        if ($record->ID && !$record->canEdit()) {
            // Restrict editing of existing records
            $form->makeReadonly();
            // Hack to re-enable delete button if user can delete
            if ($record->canDelete()) {
                $form->Actions()->fieldByName('action_doDelete')->setReadonly(false);
            }
        }
        $cannotCreate = !$record->ID && !$record->canCreate(null, $this->getCreateContext());
        if ($cannotCreate) {
            // Restrict creation of new records
            $form->makeReadonly();
        }

        // Load many_many extraData for record.
        // Fields with the correct 'ManyMany' namespace need to be added manually through getCMSFields().
        if ($list instanceof ManyManyList) {
            $extraData = $list->getExtraData('', $this->record->ID);
            $form->loadDataFrom(['ManyMany' => $extraData]);
        }

        // Coupling with CMS
        // Copied from GridFieldDetailForm_ItemRequest::ItemEditForm
        if ($controller instanceof \SilverStripe\Admin\LeftAndMain) {
            // Always show with base template (full width, no other panels),
            // regardless of overloaded CMS controller templates.
            $form->setTemplate([
                'type' => 'Includes',
                'SilverStripe\\Admin\\LeftAndMain_EditForm',
            ]);
            $form->addExtraClass('cms-content cms-edit-form center fill-height flexbox-area-grow');
            $form->setAttribute('data-pjax-fragment', 'CurrentForm Content');
            if ($form->Fields()->hasTabSet()) {
                $form->Fields()->findOrMakeTab('Root')->setTemplate('SilverStripe\\Forms\\CMSTabSet');
                $form->addExtraClass('cms-tabset');
            }

            $form->Backlink = $this->getBackLink();
        }
        if ($controller instanceof \LeKoala\Admini\LeftAndMain) {
            $form->setTemplate([
                'type' => 'Includes',
                'LeKoala\\Admini\\LeftAndMain_EditForm',
            ]);
            if ($form->Fields()->hasTabSet()) {
                $form->Fields()->findOrMakeTab('Root')->setTemplate('SilverStripe\\Forms\\CMSTabSet');
                $form->addExtraClass('cms-tabset');
            }

            $form->Backlink = $this->getBackLink();
        }

        $this->extend("updateItemEditForm", $form);

        return $form;
    }

    /**
     * Build the set of form field actions for this DataObject
     *
     * @return FieldList
     */
    protected function getFormActions()
    {
        $actions = FieldList::create();
        $majorActions = CompositeField::create()->setName('MajorActions');
        $majorActions->setFieldHolderTemplate(get_class($majorActions) . '_holder_buttongroup');
        $actions->push($majorActions);

        if ($this->record->ID !== 0) { // existing record
            if ($this->record->canEdit()) {
                $noChangesClasses = 'btn-outline-primary font-icon-tick';
                $majorActions->push(FormAction::create('doSave', _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Save', 'Save'))
                    ->addExtraClass($noChangesClasses)
                    ->setAttribute('data-btn-alternate-add', 'btn-primary font-icon-save')
                    ->setAttribute('data-btn-alternate-remove', $noChangesClasses)
                    ->setUseButtonTag(true)
                    ->setAttribute('data-text-alternate', _t('SilverStripe\\CMS\\Controllers\\CMSMain.SAVEDRAFT', 'Save')));
            }

            if ($this->record->canDelete()) {
                $actions->insertAfter('MajorActions', FormAction::create('doDelete', _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Delete', 'Delete'))
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn-outline-danger btn-hide-outline font-icon-trash-bin action--delete'));
            }
        } else { // adding new record
            //Change the Save label to 'Create'
            $majorActions->push(FormAction::create('doSave', _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Create', 'Create'))
                ->setUseButtonTag(true)
                ->addExtraClass('btn-primary font-icon-plus-thin'));

            // Add a Cancel link which is a button-like link and link back to one level up.
            $crumbs = $this->Breadcrumbs();
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

        $this->extend('updateFormActions', $actions);

        return $actions;
    }

    /**
     * Build context for verifying canCreate
     *
     * @return array
     */
    protected function getCreateContext()
    {
        $grid = $this->tabulatorGrid;
        $context = [];
        if ($grid->getList() instanceof RelationList) {
            $record = $grid->getForm()->getRecord();
            if ($record && $record instanceof DataObject) {
                $context['Parent'] = $record;
            }
        }
        return $context;
    }

    /**
     * @return CompositeField Returns the right aligned toolbar group field along with its FormAction's
     */
    protected function getRightGroupField()
    {
        $rightGroup = CompositeField::create()->setName('RightGroup');
        $rightGroup->addExtraClass('ml-auto');
        $rightGroup->setFieldHolderTemplate(get_class($rightGroup) . '_holder_buttongroup');

        $previousAndNextGroup = CompositeField::create()->setName('PreviousAndNextGroup');
        $previousAndNextGroup->addExtraClass('btn-group--circular mr-2');
        $previousAndNextGroup->setFieldHolderTemplate(CompositeField::class . '_holder_buttongroup');

        /** @var GridFieldDetailForm $component */
        $component = $this->tabulatorGrid->getConfig()->getComponentByType(GridFieldDetailForm::class);
        $paginator = $this->getGridField()->getConfig()->getComponentByType(GridFieldPaginator::class);
        $gridState = $this->getStateManager()->getStateFromRequest($this->tabulatorGrid, $this->getRequest());
        if ($component && $paginator && $component->getShowPagination()) {
            $previousIsDisabled = !$this->getPreviousRecordID();
            $nextIsDisabled = !$this->getNextRecordID();

            $previousAndNextGroup->push(
                LiteralField::create(
                    'previous-record',
                    HTML::createTag($previousIsDisabled ? 'span' : 'a', [
                        'href' => $previousIsDisabled ? '#' : $this->getEditLink($this->getPreviousRecordID()),
                        'data-grid-state' => $gridState,
                        'title' => _t(__CLASS__ . '.PREVIOUS', 'Go to previous record'),
                        'aria-label' => _t(__CLASS__ . '.PREVIOUS', 'Go to previous record'),
                        'class' => 'btn btn-secondary font-icon-left-open action--previous discard-confirmation'
                            . ($previousIsDisabled ? ' disabled' : ''),
                    ])
                )
            );

            $previousAndNextGroup->push(
                LiteralField::create(
                    'next-record',
                    HTML::createTag($nextIsDisabled ? 'span' : 'a', [
                        'href' => $nextIsDisabled ? '#' : $this->getEditLink($this->getNextRecordID()),
                        'data-grid-state' => $gridState,
                        'title' => _t(__CLASS__ . '.NEXT', 'Go to next record'),
                        'aria-label' => _t(__CLASS__ . '.NEXT', 'Go to next record'),
                        'class' => 'btn btn-secondary font-icon-right-open action--next discard-confirmation'
                            . ($nextIsDisabled ? ' disabled' : ''),
                    ])
                )
            );
        }

        $rightGroup->push($previousAndNextGroup);

        if ($component && $component->getShowAdd() && $this->record->canCreate()) {
            $rightGroup->push(
                LiteralField::create(
                    'new-record',
                    HTML::createTag('a', [
                        'href' => Controller::join_links($this->tabulatorGrid->Link('item'), 'new'),
                        'data-grid-state' => $gridState,
                        'title' => _t(__CLASS__ . '.NEW', 'Add new record'),
                        'aria-label' => _t(__CLASS__ . '.NEW', 'Add new record'),
                        'class' => 'btn btn-primary font-icon-plus-thin btn--circular action--new discard-confirmation',
                    ])
                )
            );
        }

        return $rightGroup;
    }

    /**
     */
    public function getToplevelController(): RequestHandler
    {
        $c = $this->popupController;
        // Maybe our field is included in a GridField or in a TabulatorGrid?
        while ($c && ($c instanceof GridFieldDetailForm_ItemRequest || $c instanceof TabulatorGrid_ItemRequest)) {
            $c = $c->getController();
        }
        return $c;
    }

    protected function getBackLink(): string
    {
        $backlink = '';
        $toplevelController = $this->getToplevelController();
        if ($this->popupController->hasMethod('Breadcrumbs')) {
            $parents = $this->popupController->Breadcrumbs(false);
            if ($parents && $parents = $parents->items) {
                $backlink = array_pop($parents)->Link;
            }
        }
        if ($toplevelController && $toplevelController->hasMethod('Backlink')) {
            $backlink = $toplevelController->Backlink();
        }
        if (!$backlink) {
            $backlink = $toplevelController->Link();
        }
        return $backlink;
    }

    /**
     * Get the list of extra data from the $record as saved into it by
     * {@see Form::saveInto()}
     *
     * Handles detection of falsey values explicitly saved into the
     * DataObject by formfields
     *
     * @param DataObject $record
     * @param SS_List $list
     * @return array List of data to write to the relation
     */
    protected function getExtraSavedData($record, $list)
    {
        // Skip extra data if not ManyManyList
        if (!($list instanceof ManyManyList)) {
            return null;
        }

        $data = [];
        foreach ($list->getExtraFields() as $field => $dbSpec) {
            $savedField = "ManyMany[{$field}]";
            if ($record->hasField($savedField)) {
                $data[$field] = $record->getField($savedField);
            }
        }
        return $data;
    }

    public function doSave($data, $form)
    {
        $isNewRecord = $this->record->ID == 0;

        // Check permission
        if (!$this->record->canEdit()) {
            $this->httpError(403, _t(
                __CLASS__ . '.EditPermissionsFailure',
                'It seems you don\'t have the necessary permissions to edit "{ObjectTitle}"',
                ['ObjectTitle' => $this->record->singular_name()]
            ));
            return null;
        }

        // Save from form data
        $this->saveFormIntoRecord($data, $form);

        $link = '<a href="' . $this->Link('edit') . '">"'
            . htmlspecialchars($this->record->Title, ENT_QUOTES)
            . '"</a>';
        $message = _t(
            'SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Saved',
            'Saved {name} {link}',
            [
                'name' => $this->record->i18n_singular_name(),
                'link' => $link
            ]
        );

        $form->sessionMessage($message, 'good', ValidationResult::CAST_HTML);

        // Redirect after save
        return $this->redirectAfterSave($isNewRecord);
    }

    /**
     * Gets the edit link for a record
     *
     * @param  int $id The ID of the record in the GridField
     * @return string
     */
    public function getEditLink($id)
    {
        $link = Controller::join_links(
            $this->tabulatorGrid->Link(),
            'item',
            $id
        );

        return $this->getStateManager()->addStateToURL($this->tabulatorGrid, $link);
    }

    /**
     * @param int $offset The offset from the current record
     * @return int|bool
     */
    private function getAdjacentRecordID($offset)
    {
        $gridField = $this->getGridField();
        $list = $gridField->getManipulatedList();
        $state = $gridField->getState(false);
        $gridStateStr = $this->getStateManager()->getStateFromRequest($this->tabulatorGrid, $this->getRequest());
        if (!empty($gridStateStr)) {
            $state->setValue($gridStateStr);
        }
        $data = $state->getData();
        $paginator = $data->getData('GridFieldPaginator');
        if (!$paginator) {
            return false;
        }

        $currentPage = $paginator->getData('currentPage');
        $itemsPerPage = $paginator->getData('itemsPerPage');

        $limit = $itemsPerPage + 2;
        $limitOffset = max(0, $itemsPerPage * ($currentPage - 1) - 1);

        $map = $list->limit($limit, $limitOffset)->column('ID');
        $index = array_search($this->record->ID, $map);
        return isset($map[$index + $offset]) ? $map[$index + $offset] : false;
    }

    /**
     * Gets the ID of the previous record in the list.
     *
     * @return int
     */
    public function getPreviousRecordID()
    {
        return $this->getAdjacentRecordID(-1);
    }

    /**
     * Gets the ID of the next record in the list.
     *
     * @return int
     */
    public function getNextRecordID()
    {
        return $this->getAdjacentRecordID(1);
    }

    /**
     * Response object for this request after a successful save
     *
     * @param bool $isNewRecord True if this record was just created
     * @return HTTPResponse|DBHTMLText
     */
    protected function redirectAfterSave($isNewRecord)
    {
        $controller = $this->getToplevelController();
        if ($isNewRecord) {
            return $controller->redirect($this->Link());
        } elseif ($this->tabulatorGrid->getList()->byID($this->record->ID)) {
            // Return new view, as we can't do a "virtual redirect" via the CMS Ajax
            // to the same URL (it assumes that its content is already current, and doesn't reload)
            return $this->edit($controller->getRequest());
        } else {
            // We might be able to redirect to open the record in a different view
            if ($redirectDest = $this->component->getLostRecordRedirection($this->tabulatorGrid, $controller->getRequest(), $this->record->ID)) {
                return $controller->redirect($redirectDest, 302);
            }

            // Changes to the record properties might've excluded the record from
            // a filtered list, so return back to the main view if it can't be found
            $url = $controller->getRequest()->getURL();
            $noActionURL = $controller->removeAction($url);
            $controller->getRequest()->addHeader('X-Pjax', 'Content');
            return $controller->redirect($noActionURL, 302);
        }
    }

    public function httpError($errorCode, $errorMessage = null)
    {
        $controller = $this->getToplevelController();
        return $controller->httpError($errorCode, $errorMessage);
    }

    /**
     * Loads the given form data into the underlying dataobject and relation
     *
     * @param array $data
     * @param Form $form
     * @throws ValidationException On error
     * @return DataObject Saved record
     */
    protected function saveFormIntoRecord($data, $form)
    {
        $list = $this->tabulatorGrid->getList();

        // Check object matches the correct classname
        if (isset($data['ClassName']) && $data['ClassName'] != $this->record->ClassName) {
            $newClassName = $data['ClassName'];
            // The records originally saved attribute was overwritten by $form->saveInto($record) before.
            // This is necessary for newClassInstance() to work as expected, and trigger change detection
            // on the ClassName attribute
            $this->record->setClassName($this->record->ClassName);
            // Replace $record with a new instance
            $this->record = $this->record->newClassInstance($newClassName);
        }

        // Save form and any extra saved data into this dataobject.
        // Set writeComponents = true to write has-one relations / join records
        $form->saveInto($this->record);
        // https://github.com/silverstripe/silverstripe-assets/issues/365
        $this->record->write();
        $this->extend('onAfterSave', $this->record);

        $extraData = $this->getExtraSavedData($this->record, $list);
        $list->add($this->record, $extraData);

        return $this->record;
    }

    /**
     * @param array $data
     * @param Form $form
     * @return HTTPResponse
     * @throws ValidationException
     */
    public function doDelete($data, $form)
    {
        $title = $this->record->Title;
        if (!$this->record->canDelete()) {
            throw new ValidationException(
                _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.DeletePermissionsFailure', "No delete permissions")
            );
        }
        $this->record->delete();

        $message = _t(
            'SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Deleted',
            'Deleted {type} {name}',
            [
                'type' => $this->record->i18n_singular_name(),
                'name' => htmlspecialchars($title, ENT_QUOTES)
            ]
        );

        $toplevelController = $this->getToplevelController();
        if ($toplevelController && $toplevelController instanceof \SilverStripe\Admin\LeftAndMain) {
            $backForm = $toplevelController->getEditForm();
            $backForm->sessionMessage($message, 'good', ValidationResult::CAST_HTML);
        } else {
            $form->sessionMessage($message, 'good', ValidationResult::CAST_HTML);
        }

        //when an item is deleted, redirect to the parent controller
        $controller = $this->getToplevelController();
        $controller->getRequest()->addHeader('X-Pjax', 'Content'); // Force a content refresh

        return $controller->redirect($this->getBackLink(), 302); //redirect back to admin section
    }

    /**
     * @param string $template
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Get list of templates to use
     *
     * @return array
     */
    public function getTemplates()
    {
        $templates = SSViewer::get_templates_by_class($this, '', __CLASS__);
        // Prefer any custom template
        if ($this->getTemplate()) {
            array_unshift($templates, $this->getTemplate());
        }
        return $templates;
    }

    /**
     * @return Controller
     */
    public function getController()
    {
        return $this->popupController;
    }

    /**
     * @return TabulatorGrid
     */
    public function getTabulatorGrid()
    {
        return $this->tabulatorGrid;
    }

    /**
     * @return DataObject
     */
    public function getRecord()
    {
        return $this->record;
    }

    /**
     * CMS-specific functionality: Passes through navigation breadcrumbs
     * to the template, and includes the currently edited record (if any).
     * see {@link LeftAndMain->Breadcrumbs()} for details.
     *
     * @param boolean $unlinked
     * @return ArrayList
     */
    public function Breadcrumbs($unlinked = false)
    {
        if (!$this->popupController->hasMethod('Breadcrumbs')) {
            return null;
        }

        /** @var ArrayList $items */
        $items = $this->popupController->Breadcrumbs($unlinked);

        if (!$items) {
            $items = new ArrayList();
        }

        if ($this->record && $this->record->ID) {
            $title = ($this->record->Title) ? $this->record->Title : "#{$this->record->ID}";
            $items->push(new ArrayData([
                'Title' => $title,
                'Link' => $this->Link()
            ]));
        } else {
            $items->push(new ArrayData([
                'Title' => _t('SilverStripe\\Forms\\GridField\\GridField.NewRecord', 'New {type}', ['type' => $this->record->i18n_singular_name()]),
                'Link' => false
            ]));
        }

        $this->extend('updateBreadcrumbs', $items);
        return $items;
    }
}
