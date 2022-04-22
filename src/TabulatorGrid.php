<?php

namespace LeKoala\Tabulator;

use Exception;
use RuntimeException;
use BadMethodCallException;
use SilverStripe\i18n\i18n;
use SilverStripe\Forms\Form;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\ORM\RelationList;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\Versioned\VersionedGridFieldItemRequest;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;

/**
 * @link http://www.tabulator.info/
 */
class TabulatorGrid extends FormField
{
    // @link http://www.tabulator.info/examples/5.2?#fittodata
    const LAYOUT_FIT_DATA = "fitData";
    const LAYOUT_FIT_DATA_FILL = "fitDataFill";
    const LAYOUT_FIT_DATA_STRETCH = "fitDataStretch";
    const LAYOUT_FIT_DATA_TABLE = "fitDataTable";
    const LAYOUT_FIT_COLUMNS = "fitColumns";

    const RESPONSIVE_LAYOUT_HIDE = "hide";
    const RESPONSIVE_LAYOUT_COLLAPSE = "collapse";

    // @link http://www.tabulator.info/docs/5.2/format
    const FORMATTER_PLAINTEXT = 'plaintext';
    const FORMATTER_TEXTAREA = 'textarea';
    const FORMATTER_HTML = 'html';
    const FORMATTER_MONEY = 'money';
    const FORMATTER_IMAGE = 'image';
    const FORMATTER_LINK = 'link';
    const FORMATTER_DATETIME = 'datetime';
    const FORMATTER_DATETIME_DIFF = 'datetimediff';
    const FORMATTER_TICKCROSS = 'tickCross';
    const FORMATTER_COLOR = 'color';
    const FORMATTER_STAR = 'star';
    const FORMATTER_TRAFFIC = 'traffic';
    const FORMATTER_PROGRESS = 'progress';
    const FORMATTER_LOOKUP = 'lookup';
    const FORMATTER_BUTTON_TICK = 'buttonTick';
    const FORMATTER_BUTTON_CROSS = 'buttonCross';
    const FORMATTER_ROWNUM = 'rownum';
    const FORMATTER_HANDLE = 'handle';
    // @link http://www.tabulator.info/docs/5.2/format#format-module
    const FORMATTER_ROW_SELECTION = 'rowSelection';
    const FORMATTER_RESPONSIVE_COLLAPSE = 'responsiveCollapse';

    /**
     * @config
     */
    private static array $allowed_actions = [
        'load',
        'customAction',
        'item',
    ];

    private static $url_handlers = [
        'item/$ID' => 'handleItem',
        '$Action//$CustomAction/$ID' => '$Action',
    ];

    private static array $casting = [
        'JsonOptions' => 'HTMLFragment',
    ];

    /**
     * @config
     */
    private static string $theme = 'bootstrap5';

    /**
     * @config
     */
    private static string $version = '5.2.1';

    /**
     * @config
     */
    private static string $luxon_version = '2.3.1';

    /**
     * @config
     */
    private static string $last_icon_version = '1.3.3';

    /**
     * @config
     */
    private static bool $use_cdn = true;

    /**
     * @config
     */
    private static bool $enable_luxon = false;

    /**
     * @config
     */
    private static bool $enable_last_icon = false;

    /**
     * @config
     */
    private static bool $enable_requirements = true;

    /**
     * @link http://www.tabulator.info/docs/5.2/options
     * @config
     */
    private static array $default_options = [
        'index' => "ID", // http://tabulator.info/docs/5.2/data#row-index
        'layout' => 'fitColumns', // http://www.tabulator.info/docs/5.2/layout#layout
        'height' => '100%', // http://www.tabulator.info/docs/5.2/layout#height-fixed
        'maxHeight' => "100%",
        'responsiveLayout' => "hide", // http://www.tabulator.info/docs/5.2/layout#responsive
    ];

    /**
     * @config
     */
    private static bool $use_pagination_icons = true;

    /**
     * @config
     */
    private static array $custom_pagination_icons = [];

    /**
     * @link http://www.tabulator.info/docs/5.2/columns
     */
    protected array $columns = [];

    /**
     * @link http://www.tabulator.info/docs/5.2/options
     */
    protected array $options = [];

    /**
     * Make all columns editable
     * @link http://www.tabulator.info/docs/5.2/edit
     */
    protected bool $columnsEditable = false;

    /**
     * Make all columns filterable
     * @link http://www.tabulator.info/docs/5.2/filter#header
     */
    protected bool $columnsFilterable = false;

    protected bool $autoloadDataList = true;

    protected int $pageSize = 10;

    protected string $modelClass = '';

    public function __construct($name, $title = null, $value = null)
    {
        parent::__construct($name, $title, $value);
        $this->options = self::config()->default_options ?? [];
    }

    public function configureFromDataObject($className = null, bool $clear = true): void
    {
        $this->columns = [];

        if (!$className) {
            $className = $this->getModelClass();
        }
        if (!$className) {
            throw new RuntimeException("Could not find the model class");
        }

        /** @var DataObject $singl */
        $singl = singleton($className);

        // Mock some base columns using SilverStripe built-in methods
        $columns = [];
        foreach ($singl->summaryFields() as $field => $title) {
            $columns[$field] = [
                'field' => $field,
                'title' => $title,
            ];
        }
        foreach ($singl->searchableFields() as $key => $searchOptions) {
            /*
            "filter" => "NameOfTheFilter"
            "field" => "SilverStripe\Forms\FormField"
            "title" => "Title of the field"
            */
            if (!isset($columns[$key])) {
                continue;
            }
            $columns[$key]['headerFilter'] = true;
            // $columns[$key]['headerFilterPlaceholder'] = $searchOptions['title'];
            switch ($searchOptions['filter']) {
                    //TODO: implement filter mapping
                default:
                    $columns[$key]['headerFilterFunc'] =  "like";
                    break;
            }
        }

        // Allow customizing our columns based on record
        if ($singl->hasMethod('tabulatorFields')) {
            $fields = $singl->tabulatorFields();
            if (!is_array($fields)) {
                throw new RuntimeException("tabulatorFields must return an array");
            }
            foreach ($fields as $key => $columnOptions) {
                $baseOptions = $columns[$key] ?? [];
                $columns[$key] = array_merge($baseOptions, $columnOptions);
            }
        }

        $this->extend('updateConfiguredColumns', $columns);

        foreach ($columns as $col) {
            $this->addColumn($col['field'], $col['title'], $col);
        }

        // Actions
        // We use a pseudo link, because maybe we cannot call Link() yet if it's not linked to a form

        // - Core actions
        $itemUrl = 'link:item/{ID}';
        if ($singl->canEdit()) {
            $this->addButton($itemUrl, "edit", "Edit");
        } elseif ($singl->canView()) {
            $this->addButton($itemUrl, "visibility", "View");
        }

        // - Custom actions
        if ($singl->hasMethod('tabulatorRowActions')) {
            $rowActions = $singl->tabulatorRowActions();
            if (!is_array($rowActions)) {
                throw new RuntimeException("tabulatorRowActions must return an array");
            }
            foreach ($rowActions as $key => $actionConfig) {
                $url = 'link:customAction' . '/' . $actionConfig['action'] . '/{ID}';
                $icon = $actionConfig['icon'] ?? "star";
                $title = $actionConfig['title'] ?? "";
                $this->addButton($url, $icon, $title);
            }
        }
    }

    public static function requirements(): void
    {
        $use_cdn = self::config()->use_cdn;
        $theme = self::config()->theme; // simple, midnight, modern or framework
        $version = self::config()->version;
        $luxon_version = self::config()->luxon_version;
        $enable_luxon = self::config()->enable_luxon;
        $last_icon_version = self::config()->last_icon_version;
        $enable_last_icon = self::config()->enable_last_icon;

        if ($use_cdn) {
            $baseDir = "https://cdn.jsdelivr.net/npm/tabulator-tables@$version/dist";
        } else {
            $asset = ModuleResourceLoader::resourceURL('lekoala/silverstripe-tabulator:client/cdn/tabulator.min.js');
            $baseDir = dirname($asset);
        }

        if ($luxon_version && $enable_luxon) {
            Requirements::javascript("https://cdn.jsdelivr.net/npm/luxon@$luxon_version/build/global/luxon.min.js");
        }
        if ($last_icon_version && $enable_last_icon) {
            Requirements::css("https://cdn.jsdelivr.net/npm/last-icon@$last_icon_version/last-icon.min.css");
            Requirements::javascript("https://cdn.jsdelivr.net/npm/last-icon@$last_icon_version/last-icon.min.js");
        }
        Requirements::javascript("$baseDir/js/tabulator.min.js");
        Requirements::css("$baseDir/css/tabulator.min.css");
        if ($theme) {
            Requirements::css("$baseDir/css/tabulator_$theme.min.css");
        }
        if ($theme && $theme == "bootstrap5") {
            Requirements::css('lekoala/silverstripe-tabulator:client/custom-tabulator.css');
        }
        Requirements::javascript('lekoala/silverstripe-tabulator:client/TabulatorField.js');
    }

    public function setValue($value, $data = null)
    {
        if ($value instanceof DataList) {
            $this->configureFromDataObject($value->dataClass());
        }
        return parent::setValue($value, $data);
    }

    public function Field($properties = [])
    {
        $this->addExtraClass(self::config()->theme);
        if (self::config()->enable_requirements) {
            self::requirements();
        }
        return parent::Field($properties);
    }

    public function JsonOptions(): string
    {
        $this->processButtonActions();

        $data = $this->value ?? [];
        if ($this->autoloadDataList && $data instanceof DataList) {
            $this->wizardRemotePagination();
        }

        // If remote pagination is enabled, don't load data
        if ($this->getOption('ajaxURL')) {
            $data = null;
        }

        $opts = $this->options;
        if (empty($this->columns)) {
            $opts['autoColumns'] = true;
        } else {
            $opts['columns'] = array_values($this->columns);
        }

        if (!empty($opts['columns'])) {
            foreach ($opts['columns'] as $colIdx => $colOptions) {
                if ($this->columnsEditable && !isset($colOptions['editor'])) {
                    $opts['columns'][$colIdx]['editor'] = true;
                }
                if ($this->columnsFilterable && !isset($colOptions['headerFilter'])) {
                    $opts['columns'][$colIdx]['headerFilter'] = true;
                }
            }
        }

        if ($data && is_iterable($data)) {
            if (is_iterable($data) && !is_array($data)) {
                $data = iterator_to_array($data);
            }
            $opts['data'] = $data;
        }

        // i18n
        $locale = strtolower(str_replace('_', '-', i18n::get_locale()));
        $paginationTranslations = [
            "first" => _t("TabulatorPagination.first", "First"),
            "first_title" =>  _t("TabulatorPagination.first_title", "First Page"),
            "last" =>  _t("TabulatorPagination.last", "Last"),
            "last_title" => _t("TabulatorPagination.last_title", "Last Page"),
            "prev" => _t("TabulatorPagination.prev", "Previous"),
            "prev_title" =>  _t("TabulatorPagination.prev_title", "Previous Page"),
            "next" => _t("TabulatorPagination.next", "Next"),
            "next_title" =>  _t("TabulatorPagination.next_title", "Next Page"),
            "all" =>  _t("TabulatorPagination.all", "All"),
        ];
        // This will always default to last icon if present
        if (self::config()->use_pagination_icons) {
            $customIcons = self::config()->custom_pagination_icons;
            $paginationTranslations['first'] = $customIcons['first'] ?? "<<";
            $paginationTranslations['last'] = $customIcons['last'] ?? ">>";
            $paginationTranslations['prev'] = $customIcons['prev'] ?? "<";
            $paginationTranslations['next'] = $customIcons['next'] ?? ">";
        }
        $dataTranslations = [
            "loading" => _t("TabulatorData.loading", "Loading"),
            "error" => _t("TabulatorData.error", "Error"),
        ];
        $groupsTranslations = [
            "item" => _t("TabulatorGroups.item", "Item"),
            "items" => _t("TabulatorGroups.items", "Items"),
        ];
        $headerFiltersTranslations = [
            "default" => _t("TabulatorHeaderFilters.default", "filter column..."),
        ];
        $translations = [
            'data' => $dataTranslations,
            'groups' => $groupsTranslations,
            'pagination' => $paginationTranslations,
            'headerFilters' => $headerFiltersTranslations,
        ];
        $opts['locale'] = $locale;
        $opts['langs'] = [
            $locale => $translations
        ];

        $json = json_encode($opts);

        // Escape functions
        $json = preg_replace('/"(SSTabulator\.[a-zA-Z]*)"/', "$1", $json);

        return $json;
    }

    public function getAttributes()
    {
        $attrs = parent::getAttributes();
        unset($attrs['type']);
        unset($attrs['name']);
        return $attrs;
    }

    public function getOption(string $k)
    {
        return $this->options[$k] ?? null;
    }

    public function setOption(string $k, $v): self
    {
        $this->options[$k] = $v;
        return $this;
    }

    /**
     * @link http://www.tabulator.info/docs/5.2/page#remote
     * @param string $url
     * @param array $params
     * @param integer $pageSize
     * @param integer $initialPage
     */
    public function setRemotePagination(string $url, array $params = [], int $pageSize = 0, int $initialPage = 1): self
    {
        $this->setOption("pagination", true); //enable pagination
        $this->setOption("paginationMode", 'remote'); //enable remote pagination
        $this->setOption("ajaxURL", $url); //set url for ajax request
        if (!empty($params)) {
            $this->setOption("ajaxParams", $params);
        }
        if (!$pageSize) {
            $pageSize = $this->pageSize;
        }
        $this->setOption("paginationSize", $pageSize);
        $this->setOption("paginationInitialPage", $initialPage);
        $this->setOption("paginationCounter", 'rows'); // http://www.tabulator.info/docs/5.2/page#counter
        return $this;
    }

    public function wizardRemotePagination(int $pageSize = 0, int $initialPage = 1, array $extraParams = [])
    {
        $params = array_merge([
            'SecurityID' => SecurityToken::getSecurityID()
        ], $extraParams);
        $this->setRemotePagination($this->Link('load'), $params, $pageSize, $initialPage);
        $this->setOption("sortMode", "remote"); // http://www.tabulator.info/docs/5.2/sort#ajax-sort
        $this->setOption("filterMode", "remote"); // http://www.tabulator.info/docs/5.2/filter#ajax-filter
    }

    public function setProgressiveLoad(string $url, array $params = [], int $pageSize = 0, int $initialPage = 1, string $mode = 'scroll', int $scrollMargin = 0)
    {
        $this->setOption("ajaxURL", $url);
        if (!empty($params)) {
            $this->setOption("ajaxParams", $params);
        }
        $this->setOption("progressiveLoad", $mode);
        if ($scrollMargin > 0) {
            $this->setOption("progressiveLoadScrollMargin", $scrollMargin);
        }
        if (!$pageSize) {
            $pageSize = $this->pageSize;
        }
        $this->setOption("paginationSize", $pageSize);
        $this->setOption("paginationInitialPage", $initialPage);
        $this->setOption("paginationCounter", 'rows'); // http://www.tabulator.info/docs/5.2/page#counter
    }

    public function wizardProgressiveLoad(int $pageSize = 0, int $initialPage = 1, string $mode = 'scroll', int $scrollMargin = 0, array $extraParams = [])
    {
        $params = array_merge([
            'SecurityID' => SecurityToken::getSecurityID()
        ], $extraParams);
        $this->setProgressiveLoad($this->Link('load'), $params, $pageSize, $initialPage, $mode, $scrollMargin);
        $this->setOption("sortMode", "remote"); // http://www.tabulator.info/docs/5.2/sort#ajax-sort
        $this->setOption("filterMode", "remote"); // http://www.tabulator.info/docs/5.2/filter#ajax-filter
    }

    /**
     * Builds an item edit form
     *
     * @return Form|HTTPResponse
     */
    public function ItemEditForm()
    {
        $list = $this->getDataList();
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

        $fields = $record->getCMSFields();

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

        // Copied from GridFieldDetailForm_ItemRequest::ItemEditForm
        if ($controller instanceof LeftAndMain) {
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
        $gridField = $this->gridField;
        $context = [];
        if ($gridField->getList() instanceof RelationList) {
            $record = $gridField->getForm()->getRecord();
            if ($record && $record instanceof DataObject) {
                $context['Parent'] = $record;
            }
        }
        return $context;
    }

    /**
     * This is responsible to display an edit form, like GridFieldDetailForm, but much simpler
     *
     * @return mixed
     */
    public function edit(HTTPRequest $request)
    {
        $controller = $this->getToplevelController();
        $form = $this->ItemEditForm();

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
     * @return mixed
     */
    public function view(HTTPRequest $request)
    {
        if (!$this->record->canView()) {
            $this->httpError(403, _t(
                __CLASS__ . '.ViewPermissionsFailure',
                'It seems you don\'t have the necessary permissions to view "{ObjectTitle}"',
                ['ObjectTitle' => $this->record->singular_name()]
            ));
        }

        $controller = $this->getToplevelController();

        $form = $this->ItemEditForm();
        $form->makeReadonly();

        $data = new ArrayData([
            'Backlink'     => $controller->Link(),
            'ItemEditForm' => $form
        ]);
        $return = $data->renderWith('LeKoala\\Tabulator\\TabulatorGrid_ItemEditForm');

        if ($request->isAjax()) {
            return $return;
        }
        return $controller->customise(['Content' => $return]);
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

        $dataClass = $this->getModelClass();
        $record = DataObject::get_by_id($dataClass, $ID);
        $validActions = array_column($record->tabulatorRowActions(), 'action');
        if (!$customAction || !in_array($customAction, $validActions)) {
            return $this->httpError(404, "Invalid action");
        }

        $error = false;
        try {
            $result = $record->$customAction();
        } catch (Exception $e) {
            $error = true;
            $result = $e->getMessage();
        }

        if ($result && $result instanceof HTTPResponse) {
            return $result;
        }

        // Show message on controller or in form
        $controller = $this->getToplevelController();
        $target = $this->form;
        if ($controller->hasMethod('sessionMessage')) {
            $target = $controller;
        }
        $target->sessionMessage($result, $error ? "bad" : "good");

        return $controller->redirectBack();
    }

    /**
     * @param GridField $gridField
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function handleItem($gridField, $request)
    {
        d($gridField, $request);
        // Our getController could either give us a true Controller, if this is the top-level GridField.
        // It could also give us a RequestHandler in the form of GridFieldDetailForm_ItemRequest if this is a
        // nested GridField.
        $requestHandler = $gridField->getForm()->getController();
        $record = $this->getRecordFromRequest($gridField, $request);
        if (!$record) {
            // Look for the record elsewhere in the CMS
            $redirectDest = $this->getLostRecordRedirection($gridField, $request);
            // Don't allow infinite redirections
            if ($redirectDest) {
                // Mark the remainder of the URL as parsed to trigger an immediate redirection
                while (!$request->allParsed()) {
                    $request->shift();
                }
                return (new HTTPResponse())->redirect($redirectDest);
            }

            return $requestHandler->httpError(404, 'That record was not found');
        }
        $handler = $this->getItemRequestHandler($gridField, $record, $requestHandler);
        $manager = $this->getStateManager();
        if ($gridStateStr = $manager->getStateFromRequest($gridField, $request)) {
            $gridField->getState(false)->setValue($gridStateStr);
        }

        // if no validator has been set on the GridField then use the Validators from the record.
        if (!$this->getValidator()) {
            $this->setValidator($record->getCMSCompositeValidator());
        }

        return $handler->handleRequest($request);
    }

    /**
     * @link http://www.tabulator.info/docs/5.2/page#remote-response
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function load(HTTPRequest $request)
    {
        /** @var DataList $dataList */
        $dataList = $this->value;
        if (!$dataList instanceof DataList) {
            return $this->httpError(404, "Invalid datalist");
        }

        $SecurityID = $request->getVar('SecurityID');
        if (!SecurityToken::inst()->check($SecurityID)) {
            return $this->httpError(404, "Invalid SecurityID");
        }

        $page = (int) $request->getVar('page');
        $limit = (int) $request->getVar('size');
        $offset = ($page - 1) * $limit;

        $schema = DataObject::getSchema();
        $dataClass = $dataList->dataClass();
        /** @var DataObject $singleton */
        $singleton = singleton($dataClass);
        $resolutionMap = [];

        // Sorting is an array of field/dir pairs
        $sort = $request->getVar('sort');
        $sortSql = [];
        if ($sort) {
            foreach ($sort as $sortValues) {
                $cols = array_keys($this->columns);
                $field = $sortValues['field'];
                if (!in_array($field, $cols)) {
                    throw new Exception("Invalid sort field: $field");
                }
                $dir = $sortValues['dir'];
                if (!in_array($dir, ['asc', 'desc'])) {
                    throw new Exception("Invalid sort dir: $dir");
                }

                // Nested sort
                if (strpos($field, '.') !== false) {
                    $parts = explode(".", $field);
                    if (!isset($resolutionMap[$parts[0]])) {
                        $resolutionMap[$parts[0]] = singleton($dataClass)->relObject($parts[0]);
                    }
                    $relatedObject = get_class($resolutionMap[$parts[0]]);
                    $tableName = $schema->tableForField($relatedObject, $parts[1]);
                    $baseIDColumn = $schema->sqlColumnForField($dataClass, 'ID');
                    $tableAlias = $parts[0];
                    $dataList = $dataList->leftJoin($tableName, "\"{$tableAlias}\".\"ID\" = {$baseIDColumn}", $tableAlias);
                }

                $sortSql[] = $field . ' ' . $dir;
            }
        }
        if (!empty($sortSql)) {
            $dataList = $dataList->sort(implode(", ", $sortSql));
        }

        // Filtering is an array of field/type/value arrays
        $filter = $request->getVar('filter');
        $where = [];
        if ($filter) {
            foreach ($filter as $filterValues) {
                $cols = array_keys($this->columns);
                $field = $filterValues['field'];
                if (!in_array($field, $cols)) {
                    throw new Exception("Invalid sort field: $field");
                }
                $value = $filterValues['value'];
                $type = $filterValues['type'];
                switch ($type) {
                    case "=":
                        $where["$field:nocase"] = $value;
                        break;
                    case "!=":
                        $where["$field:nocase:not"] = $value;
                        break;
                    case "like":
                        $where["$field:PartialMatch:nocase"] = $value;
                        break;
                    case "keywords":
                        $where["$field:PartialMatch:nocase"] = str_replace(" ", "%", $value);
                        break;
                    case "starts":
                        $where["$field:StartsWith:nocase"] = $value;
                        break;
                    case "ends":
                        $where["$field:EndsWith:nocase"] = $value;
                        break;
                    case "<":
                        $where["$field:LessThan:nocase"] = $value;
                        break;
                    case "<=":
                        $where["$field:LessThanOrEqual:nocase"] = $value;
                        break;
                    case ">":
                        $where["$field:GreaterThan:nocase"] = $value;
                        break;
                    case ">=":
                        $where["$field:GreaterThanOrEqual:nocase"] = $value;
                        break;
                    case "in":
                        $where["$field"] = $value;
                        break;
                    case "regex":
                        $dataList = $dataList->where('REGEXP ' . Convert::raw2sql($value));
                        break;
                    default:
                        throw new Exception("Invalid sort dir: $dir");
                }
            }
        }
        if (!empty($where)) {
            $dataList = $dataList->filter($where);
        }

        $lastRow = $dataList->count();
        $lastPage = ceil($lastRow / $limit);

        $data = [];
        /** @var DataObject $record */
        foreach ($dataList->limit($limit, $offset) as $record) {
            if ($record->hasMethod('canView') && !$record->canView()) {
                continue;
            }

            $item = [
                'ID' => $record->ID,
            ];
            $nested = [];
            foreach ($this->columns as $col) {
                $field = $col['field'] ?? null; // actions don't have field
                if (strpos($field, '.') !== false) {
                    $parts = explode('.', $field);
                    if ($singleton->getRelationClass($parts[0])) {
                        $nested[$parts[0]][] = $parts[1];
                        continue;
                    }
                }
                $item[$field] = $record->getField($field);
            }
            foreach ($nested as $nestedClass => $nestedColumns) {
                $relObject = $record->relObject($nestedClass);
                $nestedData = [];
                foreach ($nestedColumns as $nestedColumn) {
                    $nestedData[$nestedColumn] = $relObject->getField($nestedColumn);
                }
                $item[$nestedClass] = $nestedData;
            }
            $data[] = $item;
        }
        $response = new HTTPResponse(json_encode([
            'last_row' => $lastRow,
            'last_page' => $lastPage,
            'data' => $data,
        ]));
        $response->addHeader('Content-Type', 'application/json');
        return $response;
    }

    /**
     */
    public function getToplevelController(): Controller
    {
        if ($this->form) {
            $c = $this->form->getController();
        } else {
            $c = Controller::curr();
        }
        // Maybe our Tabulator field is included in a GridField ?
        while ($c && $c instanceof GridFieldDetailForm_ItemRequest) {
            $c = $c->getController();
        }
        return $c;
    }

    protected function getBackLink(): string
    {
        $backlink = '';
        $toplevelController = $this->getToplevelController();
        if ($toplevelController && $toplevelController instanceof LeftAndMain) {
            if ($toplevelController->hasMethod('Backlink')) {
                $backlink = $toplevelController->Backlink();
            }
        }
        if (!$backlink) {
            $backlink = $toplevelController->Link();
        }

        return $backlink;
    }

    public function getRecord(): DataObject
    {
        $controller = $this->getToplevelController();
        $request = $controller->getRequest();

        $modelClass = $this->getModelClass();
        $ID = (int)$request->param("ID");
        if (!$ID && is_numeric($request->param("CustomAction"))) {
            $ID = (int) $request->param("CustomAction");
        }
        if (!$ID || !$modelClass) {
            throw new RuntimeException("ID or modelClass missing");
        }
        return DataObject::get_by_id($modelClass, $ID);
    }

    public function getDataList(): DataList
    {
        if (!$this->value instanceof DataList) {
            throw new RuntimeException("Value is not a DataList, it is a: " . gettype($this->value));
        }
        return $this->value;
    }

    public function getModelClass(): ?string
    {
        if ($this->modelClass) {
            return $this->modelClass;
        }
        if ($this->value && $this->value instanceof DataList) {
            return $this->value->dataClass();
        }
        return null;
    }

    public function setModelClass(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    protected function processButtonActions()
    {
        $controller = $this->form ? $this->form->getController() : Controller::curr();
        $link = $this->Link();
        foreach ($this->columns as $name => $params) {
            if (isset($params['formatterParams']['url'])) {
                $url = $params['formatterParams']['url'];
                // It's already processed
                if (strpos($url, $link) !== false) {
                    continue;
                }
                if (strpos($url, 'link:') === 0) {
                    $url = str_replace('link:', rtrim($link, '/') . '/', $url);
                } else {
                    $url = $controller->Link($url);
                }
                $this->columns[$name]['formatterParams']['url'] = $url;
            }
        }
    }

    /**
     * @param string $action Action on the controller. Parameters between {} will be interpolated by row values.
     * @param string $icon
     * @param string $title
     * @param string|null $before
     * @return self
     */
    public function addButton(string $action, string $icon, string $title, string $before = null): self
    {
        $baseOpts = [
            "tooltip" => $title,
            "formatter" => "SSTabulator.buttonFormatter",
            "formatterParams" => [
                "icon" => $icon,
                "url" => $action, // This needs to be processed later on to make sur the field is linked to a controller
            ],
            "cellClick" => "SSTabulator.buttonHandler",
            "width" => 70,
            "hozAlign" => "center",
            "headerSort" => false,
        ];

        if ($before) {
            if (array_key_exists($before, $this->columns)) {
                $new = [];
                foreach ($this->columns as $k => $value) {
                    if ($k === $before) {
                        $new["action_$action"] = $baseOpts;
                    }
                    $new[$k] = $value;
                }
                $this->columns = $new;
            }
        } else {
            $this->columns["action_$action"] = $baseOpts;
        }

        return $this;
    }

    public function shiftButton(string $action, string $icon, string $title): self
    {
        // Find first action
        foreach ($this->columns as $name => $options) {
            if (strpos($name, 'action_') === 0) {
                return $this->addButton($action, $icon, $title, $name);
            }
        }
        return $this->addButton($action, $icon, $title);
    }

    public function removeButton(string $action): self
    {
        if (isset($this->columns["action_$action"])) {
            unset($this->columns["action_$action"]);
        }
        return $this;
    }

    /**
     * @link http://www.tabulator.info/docs/5.2/columns#definition
     * @param string $field (Required) this is the key for this column in the data array
     * @param string $title (Required) This is the title that will be displayed in the header for this column
     * @param array $opts Other options to merge in
     */
    public function addColumn(string $field, string $title = null, array $opts = []): self
    {
        if ($title === null) {
            $title = $field;
        }

        $baseOpts = [
            "field" => $field,
            "title" => $title,
        ];

        if (!empty($opts)) {
            $baseOpts = array_merge($baseOpts, $opts);
        }

        $this->columns[$field] = $baseOpts;
        return $this;
    }

    /**
     * Get column details

     * @param string $key
     */
    public function getColumn(string $key): array
    {
        if (isset($this->columns[$key])) {
            return $this->columns[$key];
        }
    }

    /**
     * Set column details
     *
     * @param string $key
     * @param array $col
     */
    public function setColumn(string $key, array $col): self
    {
        $this->columns[$key] = $col;
        return $this;
    }

    /**
     * Remove a column
     *
     * @param string $key
     */
    public function removeColumn(string $key): void
    {
        unset($this->columns[$key]);
    }


    /**
     * Get the value of columns
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Set the value of columns
     *
     * @param array $columns
     */
    public function setColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Get make all columns editable
     */
    public function getColumnsEditable(): bool
    {
        return $this->columnsEditable;
    }

    /**
     * Set make all columns editable
     */
    public function setColumnsEditable(bool $columnsEditable): self
    {
        $this->columnsEditable = $columnsEditable;
        return $this;
    }

    /**
     * Get make all columns filterable
     */
    public function getColumnsFilterable(): bool
    {
        return $this->columnsFilterable;
    }

    /**
     * Set make all columns filterable
     *
     * @param bool $columnsFilterable Make all columns filterable
     */
    public function setColumnsFilterable(bool $columnsFilterable): self
    {
        $this->columnsFilterable = $columnsFilterable;
        return $this;
    }

    /**
     * Get the value of pageSize
     */
    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Set the value of pageSize
     *
     * @param int $pageSize
     */
    public function setPageSize(int $pageSize): self
    {
        $this->pageSize = $pageSize;
        return $this;
    }

    /**
     * Get the value of autoloadDataList
     */
    public function getAutoloadDataList(): bool
    {
        return $this->autoloadDataList;
    }

    /**
     * Set the value of autoloadDataList
     *
     * @param bool $autoloadDataList
     */
    public function setAutoloadDataList(bool $autoloadDataList): self
    {
        $this->autoloadDataList = $autoloadDataList;
        return $this;
    }
}
