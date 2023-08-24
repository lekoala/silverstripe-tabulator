<?php

namespace LeKoala\Tabulator;

use Exception;
use SilverStripe\ORM\DB;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\SSViewer;
use SilverStripe\Control\Cookie;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Control\Director;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\ORM\RelationList;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Security\SecurityToken;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;

/**
 * Endpoint for actions related to a specific record
 *
 * It also allows to display a form to edit this record
 */
class TabulatorGrid_ItemRequest extends RequestHandler
{

    private static $allowed_actions = [
        'edit',
        'ajaxEdit',
        'ajaxMove',
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
     * @var array
     */
    protected $manipulatedData = null;

    /**
     * This represents the current parent RequestHandler (which does not necessarily need to be a Controller).
     * It allows us to traverse the RequestHandler chain upwards to reach the Controller stack.
     *
     * @var RequestHandler
     */
    protected $popupController;

    protected string $hash = '';

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

    protected function getManipulatedData(): array
    {
        if ($this->manipulatedData) {
            return $this->manipulatedData;
        }
        $grid = $this->getTabulatorGrid();

        $state = $grid->getState($this->popupController->getRequest());

        $currentPage = $state['page'];
        $itemsPerPage = $state['limit'];

        $limit = $itemsPerPage + 2;
        $offset = max(0, $itemsPerPage * ($currentPage - 1) - 1);

        $list = $grid->getManipulatedData($limit, $offset, $state['sort'], $state['filter']);

        $this->manipulatedData = $list;
        return $list;
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

    protected function editFailure(): HTTPResponse
    {
        return $this->httpError(403, _t(
            __CLASS__ . '.EditPermissionsFailure',
            'It seems you don\'t have the necessary permissions to edit "{ObjectTitle}"',
            ['ObjectTitle' => $this->record->singular_name()]
        ));
    }

    /**
     * This is responsible to display an edit form, like GridFieldDetailForm, but much simpler
     *
     * @return mixed
     */
    public function edit(HTTPRequest $request)
    {
        if (!$this->record->canEdit()) {
            return $this->editFailure();
        }
        $controller = $this->getToplevelController();

        $form = $this->ItemEditForm();

        return $this->returnWithinContext($request, $controller, $form);
    }

    public function ajaxEdit(HTTPRequest $request)
    {
        $SecurityID = $request->postVar('SecurityID');
        if (!SecurityToken::inst()->check($SecurityID)) {
            return $this->httpError(404, "Invalid SecurityID");
        }
        if (!$this->record->canEdit()) {
            return $this->editFailure();
        }

        $preventEmpty = [];
        if ($this->record->hasMethod('tabulatorPreventEmpty')) {
            $preventEmpty = $this->record->tabulatorPreventEmpty();
        }

        $Data = $request->postVar("Data");
        $Column = $request->postVar("Column");
        $Value = $request->postVar("Value");

        if (!$Value && in_array($Column, $preventEmpty)) {
            return $this->httpError(400, _t(__CLASS__ . '.ValueCannotBeEmpty', 'Value cannot be empty'));
        }

        try {
            $updatedValue = $this->executeEdit($Column, $Value);
        } catch (Exception $e) {
            return $this->httpError(400, $e->getMessage());
        }

        $response = new HTTPResponse(json_encode([
            'success' => true,
            'message' => _t(__CLASS__ . '.RecordEdited', 'Record edited'),
            'value' => $updatedValue,
        ]));
        $response->addHeader('Content-Type', 'application/json');
        return $response;
    }

    public function executeEdit(string $Column, $Value)
    {
        $field = $Column;
        $rel = $relField = null;
        if (strpos($Column, ".") !== false) {
            $parts = explode(".", $Column);
            $rel = $parts[0];
            $relField = $parts[1];
            $field = $rel . "ID";
            if (!is_numeric($Value)) {
                return $this->httpError(400, "ID must have a numerical value");
            }
        }
        if (!$field) {
            return $this->httpError(400, "Field must not be empty");
        }

        $this->record->$field = $Value;
        $this->record->write();
        $updatedValue = $this->record->$field;
        if ($rel) {
            /** @var DataObject $relObject */
            $relObject = $this->record->$rel();
            $updatedValue = $relObject->relField($relField);
        }
        return $updatedValue;
    }

    public function ajaxMove(HTTPRequest $request)
    {
        $SecurityID = $request->postVar('SecurityID');
        if (!SecurityToken::inst()->check($SecurityID)) {
            return $this->httpError(404, "Invalid SecurityID");
        }
        if (!$this->record->canEdit()) {
            return $this->editFailure();
        }
        $Data = $request->postVar("Data");
        if (is_string($Data)) {
            $Data = json_decode($Data, JSON_OBJECT_AS_ARRAY);
        }
        $Sort = $request->postVar("Sort");

        try {
            $updatedSort = $this->executeSort($Data, $Sort);
        } catch (Exception $e) {
            return $this->httpError(400, $e->getMessage());
        }

        $response = new HTTPResponse(json_encode([
            'success' => true,
            'message' => _t(__CLASS__ . '.RecordMove', 'Record moved'),
            'value' => $updatedSort,
        ]));
        $response->addHeader('Content-Type', 'application/json');
        return $response;
    }

    public function executeSort(array $Data, int $Sort, string $sortField = 'Sort'): int
    {
        $table = DataObject::getSchema()->baseDataTable(get_class($this->record));

        if (!isset($Data[$sortField])) {
            return $this->httpError(403, _t(
                __CLASS__ . '.UnableToResolveSort',
                'Unable to resolve previous sort order'
            ));
        }

        $prevSort = $Data[$sortField];

        // Just make sure you don't have 0 (except first record) or equal sorts
        if ($prevSort < $Sort) {
            $set = "$sortField = $sortField - 1";
            $where = "$sortField > $prevSort and $sortField <= $Sort";
        } else {
            $set = "$sortField = $sortField + 1";
            $where = "$sortField < $prevSort and $sortField >= $Sort";
        }
        DB::query("UPDATE `$table` SET $set WHERE $where");
        $this->record->$sortField = $Sort;
        $this->record->write();

        return $this->record->Sort;
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
        $response = $controller->getResponse();
        if (Director::is_ajax()) {
            $responseData = [
                'message' => $result,
                'status' => $error ? 'error' : 'success',
            ];
            if (!empty($clickedAction['reload'])) {
                $responseData['reload'] = true;
            }
            if (!empty($clickedAction['refresh'])) {
                $responseData['refresh'] = true;
            }
            $response->setBody(json_encode($responseData));
            // 4xx status makes a red box
            if ($error) {
                $response->setStatusCode(400);
            }
            return $response;
        }

        $this->sessionMessage($result, $error ? "error" : "good", "html");

        $url = $this->getDefaultBackLink();
        return $this->redirect($url);
    }

    public function sessionMessage($message, $type = ValidationResult::TYPE_ERROR, $cast = ValidationResult::CAST_TEXT)
    {
        $controller = $this->getToplevelController();
        if ($controller->hasMethod('sessionMessage')) {
            $controller->sessionMessage($message, $type, $cast);
        } else {
            $form = $this->ItemEditForm();
            if ($form) {
                $form->sessionMessage($message, $type, $cast);
            }
        }
    }

    /**
     * Builds an item edit form
     *
     * @return Form|HTTPResponse
     */
    public function ItemEditForm()
    {
        $list = $this->tabulatorGrid->getList();
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

        $compatLayer = $this->tabulatorGrid->getCompatLayer($controller);

        $actions = $compatLayer->getFormActions($this);
        $this->extend('updateFormActions', $actions);

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
        if ($cannotCreate || $this->tabulatorGrid->isViewOnly()) {
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
        $compatLayer->adjustItemEditForm($this, $form);

        $this->extend("updateItemEditForm", $form);

        return $form;
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
     * @return \SilverStripe\Control\Controller|\SilverStripe\Admin\LeftAndMain|TabulatorGrid_ItemRequest
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

    public function getDefaultBackLink(): string
    {
        $url = $this->getBackURL()
            ?: $this->getReturnReferer()
            ?: $this->AbsoluteLink();
        return $url;
    }

    public function getBackLink(): string
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
            return $this->editFailure();
        }

        // _activetab is used in cms-action
        $this->hash = $data['_hash'] ?? $data['_activetab'] ?? '';

        // Save from form data
        $error = false;
        try {
            $this->saveFormIntoRecord($data, $form);

            $title = $this->record->Title ?? '';
            $link = '<a href="' . $this->Link('edit') . '">"'
                . htmlspecialchars($title, ENT_QUOTES)
                . '"</a>';
            $message = _t(
                'SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Saved',
                'Saved {name} {link}',
                [
                    'name' => $this->record->i18n_singular_name(),
                    'link' => $link
                ]
            );
        } catch (Exception $e) {
            $message = $e->getMessage();
            $error = true;
        }

        $this->sessionMessage($message, $error ? "error" : "good", 'html');

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

        return $link;
    }

    /**
     * @param int $offset The offset from the current record
     * @return int|bool
     */
    private function getAdjacentRecordID($offset)
    {
        $list = $this->getManipulatedData();
        $map = array_column($list['data'], "ID");
        $index = array_search($this->record->ID, $map);
        return isset($map[$index + $offset]) ? $map[$index + $offset] : false;
    }

    /**
     * Gets the ID of the previous record in the list.
     */
    public function getPreviousRecordID(): int
    {
        return $this->getAdjacentRecordID(-1);
    }

    /**
     * Gets the ID of the next record in the list.
     */
    public function getNextRecordID(): int
    {
        return $this->getAdjacentRecordID(1);
    }

    /**
     * This is expected in lekoala/silverstripe-cms-actions ActionsGridFieldItemRequest
     * @return HTTPResponse
     */
    public function getResponse()
    {
        return $this->getToplevelController()->getResponse();
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
            return $this->redirect($this->Link());
        } elseif ($this->tabulatorGrid->hasByIDList() && $this->tabulatorGrid->getByIDList()->byID($this->record->ID)) {
            return $this->redirect($this->getDefaultBackLink());
        } else {
            // Changes to the record properties might've excluded the record from
            // a filtered list, so return back to the main view if it can't be found
            $url = $controller->getRequest()->getURL();
            $noActionURL = $controller->removeAction($url);
            if ($this->isSilverStripeAdmin($controller)) {
                $controller->getRequest()->addHeader('X-Pjax', 'Content');
            }
            return $controller->redirect($noActionURL, 302);
        }
    }

    protected function getHashValue()
    {
        if ($this->hash) {
            $hash = $this->hash;
        } else {
            $hash = Cookie::get('hash');
        }
        if ($hash) {
            $hash = '#' . ltrim($hash, '#');
        }
        return $hash;
    }

    /**
     * Redirect to the given URL.
     *
     * @param string $url
     * @param int $code
     * @return HTTPResponse
     */
    public function redirect($url, $code = 302): HTTPResponse
    {
        $hash = $this->getHashValue();
        if ($hash) {
            $url .= $hash;
        }
        $response = parent::redirect($url, $code);

        // if ($hash) {
        // We also pass it as a hash
        // @link https://github.com/whatwg/fetch/issues/1167
        // $response = $response->addHeader('X-Hash', $hash);
        // }

        return $response;
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


        $backForm = $form;
        $toplevelController = $this->getToplevelController();
        if ($this->isSilverStripeAdmin($toplevelController)) {
            $backForm = $toplevelController->getEditForm();
        }
        $this->sessionMessage($message, "good");

        //when an item is deleted, redirect to the parent controller
        $controller = $this->getToplevelController();

        if ($this->isSilverStripeAdmin($toplevelController)) {
            $controller->getRequest()->addHeader('X-Pjax', 'Content');
        }
        return $controller->redirect($this->getBackLink(), 302); //redirect back to admin section
    }

    public function isSilverStripeAdmin($controller)
    {
        if ($controller) {
            return is_subclass_of($controller, \SilverStripe\Admin\LeftAndMain::class);
        }
        return false;
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
