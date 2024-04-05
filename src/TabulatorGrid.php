<?php

namespace LeKoala\Tabulator;

use Exception;
use RuntimeException;
use SilverStripe\i18n\i18n;
use SilverStripe\Forms\Form;
use InvalidArgumentException;
use LeKoala\Tabulator\BulkActions\BulkDeleteAction;
use SilverStripe\ORM\SS_List;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Control\Director;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\FieldType\DBEnum;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\SecurityToken;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\ORM\Filters\PartialMatchFilter;

/**
 * This is a replacement for most GridField usages in SilverStripe
 * It can easily work in the frontend too
 *
 * @link http://www.tabulator.info/
 */
class TabulatorGrid extends FormField
{
    const POS_START = 'start';
    const POS_END = 'end';

    const UI_EDIT = "ui_edit";
    const UI_DELETE = "ui_delete";
    const UI_UNLINK = "ui_unlink";
    const UI_VIEW = "ui_view";
    const UI_SORT = "ui_sort";

    const TOOL_ADD_NEW = "add_new";
    const TOOL_EXPORT = "export"; // xlsx
    const TOOL_EXPORT_CSV = "export_csv";
    const TOOL_ADD_EXISTING = "add_existing";

    // @link http://www.tabulator.info/examples/5.5?#fittodata
    const LAYOUT_FIT_DATA = "fitData";
    const LAYOUT_FIT_DATA_FILL = "fitDataFill";
    const LAYOUT_FIT_DATA_STRETCH = "fitDataStretch";
    const LAYOUT_FIT_DATA_TABLE = "fitDataTable";
    const LAYOUT_FIT_COLUMNS = "fitColumns";

    const RESPONSIVE_LAYOUT_HIDE = "hide";
    const RESPONSIVE_LAYOUT_COLLAPSE = "collapse";

    // @link http://www.tabulator.info/docs/5.5/format
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
    // @link http://www.tabulator.info/docs/5.5/format#format-module
    const FORMATTER_ROW_SELECTION = 'rowSelection';
    const FORMATTER_RESPONSIVE_COLLAPSE = 'responsiveCollapse';

    // our built in functions
    const JS_BOOL_GROUP_HEADER = 'SSTabulator.boolGroupHeader';
    const JS_DATA_AJAX_RESPONSE = 'SSTabulator.dataAjaxResponse';
    const JS_INIT_CALLBACK = 'SSTabulator.initCallback';
    const JS_CONFIG_CALLBACK = 'SSTabulator.configCallback';

    /**
     * @config
     */
    private static array $allowed_actions = [
        'load',
        'handleItem',
        'handleTool',
        'configProvider',
        'autocomplete',
        'handleBulkAction',
    ];

    private static $url_handlers = [
        'item/$ID' => 'handleItem',
        'tool/$ID//$OtherID' => 'handleTool',
        'bulkAction/$ID' => 'handleBulkAction',
    ];

    private static array $casting = [
        'JsonOptions' => 'HTMLFragment',
        'ShowTools' => 'HTMLFragment',
        'dataAttributesHTML' => 'HTMLFragment',
    ];

    /**
     * @config
     */
    private static bool $load_styles = true;

    /**
     * @config
     */
    private static string $luxon_version = '3';

    /**
     * @config
     */
    private static string $last_icon_version = '2';

    /**
     * @config
     */
    private static bool $use_cdn = false;

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
     * @config
     */
    private static bool $enable_js_modules = true;

    /**
     * @link http://www.tabulator.info/docs/5.5/options
     * @config
     */
    private static array $default_options = [
        'index' => "ID", // http://tabulator.info/docs/5.5/data#row-index
        'layout' => 'fitColumns', // http://www.tabulator.info/docs/5.5/layout#layout
        'height' => '100%', // http://www.tabulator.info/docs/5.5/layout#height-fixed
        'responsiveLayout' => "hide", // http://www.tabulator.info/docs/5.5/layout#responsive
    ];

    /**
     * @link http://tabulator.info/docs/5.5/columns#defaults
     * @config
     */
    private static array $default_column_options = [
        'resizable' => false,
    ];

    private static bool $enable_ajax_init = true;

    /**
     * @config
     */
    private static bool $default_lazy_init = false;

    /**
     * @config
     */
    private static bool $show_row_delete = false;

    /**
     * Data source.
     */
    protected ?SS_List $list;

    /**
     * @link http://www.tabulator.info/docs/5.5/columns
     */
    protected array $columns = [];

    /**
     * @link http://tabulator.info/docs/5.5/columns#defaults
     */
    protected array $columnDefaults = [];

    /**
     * @link http://www.tabulator.info/docs/5.5/options
     */
    protected array $options = [];

    protected bool $autoloadDataList = true;

    protected bool $rowClickTriggersAction = false;

    protected int $pageSize = 10;

    protected string $itemRequestClass = '';

    protected string $modelClass = '';

    protected bool $lazyInit = false;

    protected array $tools = [];

    /**
     * @var AbstractBulkAction[]
     */
    protected array $bulkActions = [];

    protected array $listeners = [];

    protected array $linksOptions = [
        'ajaxURL'
    ];

    protected array $dataAttributes = [];

    protected string $controllerFunction = "";

    protected string $editUrl = "";

    protected string $moveUrl = "";

    protected string $bulkUrl = "";

    protected bool $globalSearch = false;

    protected array $wildcardFields = [];

    protected array $quickFilters = [];

    protected string $defaultFilter = 'PartialMatch';

    protected bool $groupLayout = false;

    protected bool $enableGridManipulation = false;

    /**
     * @param string $fieldName
     * @param string|null|bool $title
     * @param SS_List $value
     */
    public function __construct($name, $title = null, $value = null)
    {
        // Set options and defaults first
        $this->options = self::config()->default_options ?? [];
        $this->columnDefaults = self::config()->default_column_options ?? [];

        parent::__construct($name, $title, $value);
        $this->setLazyInit(self::config()->default_lazy_init);

        // We don't want regular setValue for this since it would break with loadFrom logic
        if ($value) {
            $this->setList($value);
        }
    }

    /**
     * This helps if some third party code expects the TabulatorGrid to be a GridField
     * Only works to a really basic extent
     */
    public function getConfig(): GridFieldConfig
    {
        return new GridFieldConfig;
    }

    /**
     * This helps if some third party code expects the TabulatorGrid to be a GridField
     * Only works to a really basic extent
     */
    public function setConfig($config)
    {
        // ignore
    }

    /**
     * @return string
     */
    public function getValueJson()
    {
        $v = $this->value ?? '';
        if (is_array($v)) {
            $v = json_encode($v);
        }
        if (strpos($v, '[') !== 0) {
            return '[]';
        }
        return $v;
    }

    public function saveInto(DataObjectInterface $record)
    {
        if ($this->enableGridManipulation) {
            $value = $this->dataValue();
            if (is_array($value)) {
                $this->value = json_encode(array_values($value));
            }
            parent::saveInto($record);
        }
    }

    /**
     * Temporary link that will be replaced by a real link by processLinks
     * TODO: not really happy with this, find a better way
     *
     * @param string $action
     * @return string
     */
    public function TempLink(string $action, bool $controller = true): string
    {
        // It's an absolute link
        if (strpos($action, '/') === 0 || strpos($action, 'http') === 0) {
            return $action;
        }
        // Already temp
        if (strpos($action, ':') !== false) {
            return $action;
        }
        $prefix = $controller ? "controller" : "form";
        return "$prefix:$action";
    }

    public function ControllerLink(string $action): string
    {
        return $this->getForm()->getController()->Link($action);
    }

    public function getCreateLink(): string
    {
        return Controller::join_links($this->Link('item'), 'new');
    }

    /**
     * @param FieldList $fields
     * @param string $name
     * @return TabulatorGrid|null
     */
    public static function replaceGridField(FieldList $fields, string $name)
    {
        /** @var \SilverStripe\Forms\GridField\GridField $gridField */
        $gridField = $fields->dataFieldByName($name);
        if (!$gridField) {
            return;
        }
        if ($gridField instanceof TabulatorGrid) {
            return $gridField;
        }
        $tabulatorGrid = new TabulatorGrid($name, $gridField->Title(), $gridField->getList());
        // In the cms, this is mostly never happening
        if ($gridField->getForm()) {
            $tabulatorGrid->setForm($gridField->getForm());
        }
        $tabulatorGrid->configureFromDataObject($gridField->getModelClass());
        $tabulatorGrid->setLazyInit(true);
        $fields->replaceField($name, $tabulatorGrid);

        return $tabulatorGrid;
    }

    /**
     * A shortcut to convert editable records to view only
     * Disables adding new records as well
     */
    public function setViewOnly(): void
    {
        $itemUrl = $this->TempLink('item/{ID}', false);
        $this->removeButton(self::UI_EDIT);
        $this->removeButton(self::UI_DELETE);
        $this->removeButton(self::UI_UNLINK);
        $this->addButton(self::UI_VIEW, $itemUrl, "visibility", "View");
        $this->removeTool(TabulatorAddNewButton::class);
    }

    public function isViewOnly(): bool
    {
        return !$this->hasButton(self::UI_EDIT);
    }

    public function setManageRelations(): array
    {
        $this->addToolEnd($AddExistingAutocompleter = new TabulatorAddExistingAutocompleter());

        $unlinkBtn = $this->makeButton($this->TempLink('item/{ID}/unlink', false), "link_off", _t('TabulatorGrid.Unlink', 'Unlink'));
        $unlinkBtn["formatterParams"]["classes"] = 'btn btn-danger';
        $unlinkBtn['formatterParams']['ajax'] = true;
        $this->addButtonFromArray(self::UI_UNLINK, $unlinkBtn);

        return [
            $AddExistingAutocompleter,
            $unlinkBtn
        ];
    }

    protected function getTabulatorOptions(DataObject $singl)
    {
        $opts = [];
        if ($singl->hasMethod('tabulatorOptions')) {
            $opts = $singl->tabulatorOptions();
        }
        return $opts;
    }

    public function configureFromDataObject($className = null): void
    {
        $this->columns = [];

        if (!$className) {
            $className = $this->getModelClass();
        }
        if (!$className) {
            throw new RuntimeException("Could not find the model class");
        }
        $this->modelClass = $className;

        /** @var DataObject $singl */
        $singl = singleton($className);
        $opts = $this->getTabulatorOptions($singl);

        // Mock some base columns using SilverStripe built-in methods
        $columns = [];

        $summaryFields = $opts['summaryFields'] ?? $singl->summaryFields();
        foreach ($summaryFields as $field => $title) {
            // Deal with this in load() instead
            // if (strpos($field, '.') !== false) {
            // $fieldParts = explode(".", $field);

            // It can be a relation Users.Count or a field Field.Nice
            // $classOrField = $fieldParts[0];
            // $relationOrMethod = $fieldParts[1];
            // }
            $title = str_replace(".", " ", $title);
            $columns[$field] = [
                'field' => $field,
                'title' => $title,
            ];

            $dbObject = $singl->dbObject($field);
            if ($dbObject) {
                if ($dbObject instanceof DBBoolean) {
                    $columns[$field]['formatter'] = "customTickCross";
                }
            }
        }
        $searchableFields = $opts['searchableFields'] ?? $singl->searchableFields();
        $searchAliases = $opts['searchAliases'] ?? [];
        foreach ($searchableFields as $key => $searchOptions) {
            $key = $searchAliases[$key] ?? $key;

            // Allow "nice"
            if (isset($columns[$key . ".Nice"])) {
                $key = $key . ".Nice";
            }

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
            //TODO: implement filter mapping
            switch ($searchOptions['filter']) {
                default:
                    $columns[$key]['headerFilterFunc'] =  "like";
                    break;
            }

            // Restrict based on data type
            $dbObject = $singl->dbObject($key);
            if ($dbObject) {
                if ($dbObject instanceof DBBoolean) {
                    $columns[$key]['headerFilter'] = 'tickCross';
                    $columns[$key]['headerFilterFunc'] =  "=";
                    $columns[$key]['headerFilterParams'] =  [
                        'tristate' => true
                    ];
                }
                if ($dbObject instanceof DBEnum) {
                    $columns[$key]['headerFilter'] = 'list';
                    $columns[$key]['headerFilterFunc'] =  "=";
                    $columns[$key]['headerFilterParams'] =  [
                        'values' => $dbObject->enumValues()
                    ];
                }
            }
        }

        // Allow customizing our columns based on record
        if ($singl->hasMethod('tabulatorColumns')) {
            $fields = $singl->tabulatorColumns();
            if (!is_array($fields)) {
                throw new RuntimeException("tabulatorColumns must return an array");
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

        // Sortable ?
        $sortable = $opts['sortable'] ?? $singl->hasField('Sort');
        if ($sortable) {
            $this->wizardMoveable();
        }

        // Actions
        // We use a pseudo link, because maybe we cannot call Link() yet if it's not linked to a form

        $this->bulkUrl = $this->TempLink("bulkAction/", false);

        // - Core actions, handled by TabulatorGrid
        $itemUrl = $this->TempLink('item/{ID}', false);
        if ($singl->canEdit()) {
            $this->addEditButton();
        } elseif ($singl->canView()) {
            $this->addButton(self::UI_VIEW, $itemUrl, "visibility", _t('TabulatorGrid.View', 'View'));
        }

        $showRowDelete = $opts['rowDelete'] ?? self::config()->show_row_delete;
        if ($singl->canDelete() && $showRowDelete) {
            $deleteBtn = $this->makeButton($this->TempLink('item/{ID}/delete', false), "delete", _t('TabulatorGrid.Delete', 'Delete'));
            $deleteBtn["formatterParams"]["classes"] = 'btn btn-danger';
            $this->addButtonFromArray(self::UI_DELETE, $deleteBtn);
        }

        // - Tools
        $this->tools = [];

        $addNew = $opts['addNew'] ?? true;
        if ($singl->canCreate() && $addNew) {
            $this->addTool(self::POS_START, new TabulatorAddNewButton($this), self::TOOL_ADD_NEW);
        }
        $export = $opts['export'] ?? true;
        if (class_exists(\LeKoala\ExcelImportExport\ExcelImportExport::class) && $export) {
            $xlsxExportButton = new TabulatorExportButton($this);
            $this->addTool(self::POS_END, $xlsxExportButton, self::TOOL_EXPORT);
            $csvExportButton = new TabulatorExportButton($this);
            $csvExportButton->setExportFormat('csv');
            $this->addTool(self::POS_END, $csvExportButton, self::TOOL_EXPORT_CSV);
        }

        // - Custom actions are forwarded to the model itself
        if ($singl->hasMethod('tabulatorRowActions')) {
            $rowActions = $singl->tabulatorRowActions();
            if (!is_array($rowActions)) {
                throw new RuntimeException("tabulatorRowActions must return an array");
            }
            foreach ($rowActions as $key => $actionConfig) {
                $action = $actionConfig['action'] ?? $key;
                $url = $this->TempLink("item/{ID}/customAction/$action", false);
                $icon = $actionConfig['icon'] ?? "cog";
                $title = $actionConfig['title'] ?? "";

                $button = $this->makeButton($url, $icon, $title);
                if (!empty($actionConfig['ajax'])) {
                    $button['formatterParams']['ajax'] = true;
                }
                $this->addButtonFromArray("ui_customaction_$action", $button);
            }
        }

        $this->setRowClickTriggersAction(true);
    }

    public static function requirements(): void
    {
        $load_styles = self::config()->load_styles;
        $luxon_version = self::config()->luxon_version;
        $enable_luxon = self::config()->enable_luxon;
        $last_icon_version = self::config()->last_icon_version;
        $enable_last_icon = self::config()->enable_last_icon;
        $enable_js_modules = self::config()->enable_js_modules;

        $jsOpts = [];
        if ($enable_js_modules) {
            $jsOpts['type'] = 'module';
        }

        if ($luxon_version && $enable_luxon) {
            // Do not load as module or we would get undefined luxon global var
            Requirements::javascript("https://cdn.jsdelivr.net/npm/luxon@$luxon_version/build/global/luxon.min.js");
        }
        if ($last_icon_version && $enable_last_icon) {
            Requirements::css("https://cdn.jsdelivr.net/npm/last-icon@$last_icon_version/last-icon.min.css");
            // Do not load as module even if asked to ensure load speed
            Requirements::javascript("https://cdn.jsdelivr.net/npm/last-icon@$last_icon_version/last-icon.min.js");
        }

        Requirements::javascript('lekoala/silverstripe-tabulator:client/TabulatorField.js', $jsOpts);
        if ($load_styles) {
            Requirements::css('lekoala/silverstripe-tabulator:client/custom-tabulator.css');
            Requirements::javascript('lekoala/silverstripe-tabulator:client/tabulator-grid.min.js', $jsOpts);
        } else {
            // you must load th css yourself based on your preferences
            Requirements::javascript('lekoala/silverstripe-tabulator:client/tabulator-grid.raw.min.js', $jsOpts);
        }
    }

    public function setValue($value, $data = null)
    {
        // Allow set raw json as value
        if ($value && is_string($value) && strpos($value, '[') === 0) {
            $value = json_decode($value);
        }
        if ($value instanceof DataList) {
            $this->configureFromDataObject($value->dataClass());
        }
        return parent::setValue($value, $data);
    }

    public function Field($properties = [])
    {
        if (self::config()->enable_requirements) {
            self::requirements();
        }

        // Make sure we can use a standalone version of the field without a form
        // Function should match the name
        if (!$this->form) {
            $this->form = new Form(Controller::curr(), $this->getControllerFunction());
        }

        // Data attributes for our custom behaviour
        $this->setDataAttribute("row-click-triggers-action", $this->rowClickTriggersAction);

        $this->setDataAttribute("listeners", $this->listeners);
        if ($this->editUrl) {
            $url = $this->processLink($this->editUrl);
            $this->setDataAttribute("edit-url", $url);
        }
        if ($this->moveUrl) {
            $url = $this->processLink($this->moveUrl);
            $this->setDataAttribute("move-url", $url);
        }
        if (!empty($this->bulkActions)) {
            $url = $this->processLink($this->bulkUrl);
            $this->setDataAttribute("bulk-url", $url);
        }

        return parent::Field($properties);
    }

    public function ShowTools(): string
    {
        if (empty($this->tools)) {
            return '';
        }
        $html = '';
        $html .= '<div class="tabulator-tools">';
        $html .= '<div class="tabulator-tools-start">';
        foreach ($this->tools as $tool) {
            if ($tool['position'] != self::POS_START) {
                continue;
            }
            $html .= ($tool['tool'])->forTemplate();
        }
        $html .= '</div>';
        $html .= '<div class="tabulator-tools-end">';
        foreach ($this->tools as $tool) {
            if ($tool['position'] != self::POS_END) {
                continue;
            }
            $html .= ($tool['tool'])->forTemplate();
        }
        // Show bulk actions at the end
        if (!empty($this->bulkActions)) {
            $selectLabel = _t(__CLASS__ . ".BULKSELECT", "Select a bulk action");
            $confirmLabel = _t(__CLASS__ . ".BULKCONFIRM", "Go");
            $html .= "<select class=\"tabulator-bulk-select\">";
            $html .= "<option>" . $selectLabel . "</option>";
            foreach ($this->bulkActions as $bulkAction) {
                $v = $bulkAction->getName();
                $xhr = $bulkAction->getXhr();
                $destructive = $bulkAction->getDestructive();
                $html .= "<option value=\"$v\" data-xhr=\"$xhr\" data-destructive=\"$destructive\">" . $bulkAction->getLabel() . "</option>";
            }
            $html .= "</select>";
            $html .= "<button class=\"tabulator-bulk-confirm btn\">" . $confirmLabel . "</button>";
        }
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    public function JsonOptions(): string
    {
        $this->processLinks();

        $data = $this->list ?? [];
        if ($this->autoloadDataList && $data instanceof DataList) {
            $data = null;
        }
        $opts = $this->options;
        $opts['columnDefaults'] = $this->columnDefaults;

        if (empty($this->columns)) {
            $opts['autoColumns'] = true;
        } else {
            $opts['columns'] = array_values($this->columns);
        }

        if ($data && is_iterable($data)) {
            if ($data instanceof ArrayList) {
                $data = $data->toArray();
            } else {
                if (is_iterable($data) && !is_array($data)) {
                    $data = iterator_to_array($data);
                }
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
        $bulkActionsTranslations = [
            "no_action" => _t("TabulatorBulkActions.no_action", "Please select an action"),
            "no_records" => _t("TabulatorBulkActions.no_records", "Please select a record"),
            "destructive" => _t("TabulatorBulkActions.destructive", "Confirm destructive action ?"),
        ];
        $translations = [
            'data' => $dataTranslations,
            'groups' => $groupsTranslations,
            'pagination' => $paginationTranslations,
            'headerFilters' => $headerFiltersTranslations,
            'bulkActions' => $bulkActionsTranslations,
        ];
        $opts['locale'] = $locale;
        $opts['langs'] = [
            $locale => $translations
        ];

        // Apply state
        // TODO: finalize persistence on the client side instead of this when using TabID
        $state = $this->getState();
        if ($state) {
            if (!empty($state['filter'])) {
                // @link https://tabulator.info/docs/5.5/filter#initial
                // We need to split between global filters and header filters
                $allFilters = $state['filter'] ?? [];
                $globalFilters = [];
                $headerFilters = [];
                foreach ($allFilters as $allFilter) {
                    if (strpos($allFilter['field'], '__') === 0) {
                        $globalFilters[] = $allFilter;
                    } else {
                        $headerFilters[] = $allFilter;
                    }
                }
                $opts['initialFilter'] = $globalFilters;
                $opts['initialHeaderFilter'] = $headerFilters;
            }
            if (!empty($state['sort'])) {
                // @link https://tabulator.info/docs/5.5/sort#initial
                $opts['initialSort'] = $state['sort'];
            }

            // Restore state from server
            $opts['_state'] = $state;
        }

        if ($this->enableGridManipulation) {
            // $opts['renderVertical'] = 'basic';
        }

        // Add our extension initCallback
        $opts['_initCallback'] = ['__fn' => self::JS_INIT_CALLBACK];
        $opts['_configCallback'] = ['__fn' => self::JS_CONFIG_CALLBACK];

        unset($opts['height']);
        $json = json_encode($opts);

        // Escape '
        $json = str_replace("'", '&#39;', $json);

        return $json;
    }

    /**
     * @param Controller $controller
     * @return CompatLayerInterface
     */
    public function getCompatLayer(Controller $controller = null)
    {
        if ($controller === null) {
            $controller = Controller::curr();
        }
        if (is_subclass_of($controller, \SilverStripe\Admin\LeftAndMain::class)) {
            return new SilverstripeAdminCompat();
        }
        if (is_subclass_of($controller, \LeKoala\Admini\LeftAndMain::class)) {
            return new AdminiCompat();
        }
    }

    public function getAttributes()
    {
        $attrs = parent::getAttributes();
        unset($attrs['type']);
        unset($attrs['name']);
        unset($attrs['value']);
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

    public function getRowHeight(): int
    {
        return $this->getOption('rowHeight');
    }

    /**
     * Prevent row height automatic computation
     * @link https://tabulator.info/docs/5.5/layout#height-row
     */
    public function setRowHeight(int $v): self
    {
        $this->setOption('rowHeight', $v);
        return $this;
    }

    public function makeHeadersSticky(): self
    {
        // note: we could also use the "sticky" attribute on the custom element
        $this->addExtraClass("tabulator-sticky");
        return $this;
    }

    public function setRemoteSource(string $url, array $extraParams = [], bool $dataResponse = false): self
    {
        $this->setOption("ajaxURL", $url); //set url for ajax request
        $params = array_merge([
            'SecurityID' => SecurityToken::getSecurityID()
        ], $extraParams);
        $this->setOption("ajaxParams", $params);
        // Accept response where data is nested under the data key
        if ($dataResponse) {
            $this->setOption("ajaxResponse", ['__fn' => self::JS_DATA_AJAX_RESPONSE]);
        }
        return $this;
    }

    /**
     * @link http://www.tabulator.info/docs/5.5/page#remote
     * @param string $url
     * @param array $params
     * @param integer $pageSize
     * @param integer $initialPage
     */
    public function setRemotePagination(string $url, array $params = [], int $pageSize = 0, int $initialPage = 1): self
    {
        $this->setOption("pagination", true); //enable pagination
        $this->setOption("paginationMode", 'remote'); //enable remote pagination
        $this->setRemoteSource($url, $params);
        if (!$pageSize) {
            $pageSize = $this->pageSize;
        } else {
            $this->pageSize = $pageSize;
        }
        $this->setOption("paginationSize", $pageSize);
        $this->setOption("paginationInitialPage", $initialPage);
        $this->setOption("paginationCounter", 'rows'); // http://www.tabulator.info/docs/5.5/page#counter
        return $this;
    }

    public function wizardRemotePagination(int $pageSize = 0, int $initialPage = 1, array $params = []): self
    {
        $this->setRemotePagination($this->TempLink('load', false), $params, $pageSize, $initialPage);
        $this->setOption("sortMode", "remote"); // http://www.tabulator.info/docs/5.5/sort#ajax-sort
        $this->setOption("filterMode", "remote"); // http://www.tabulator.info/docs/5.5/filter#ajax-filter
        return $this;
    }

    public function setProgressiveLoad(string $url, array $params = [], int $pageSize = 0, int $initialPage = 1, string $mode = 'scroll', int $scrollMargin = 0): self
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
        } else {
            $this->pageSize = $pageSize;
        }
        $this->setOption("paginationSize", $pageSize);
        $this->setOption("paginationInitialPage", $initialPage);
        $this->setOption("paginationCounter", 'rows'); // http://www.tabulator.info/docs/5.5/page#counter
        return $this;
    }

    public function wizardProgressiveLoad(int $pageSize = 0, int $initialPage = 1, string $mode = 'scroll', int $scrollMargin = 0, array $extraParams = []): self
    {
        $params = array_merge([
            'SecurityID' => SecurityToken::getSecurityID()
        ], $extraParams);
        $this->setProgressiveLoad($this->TempLink('load', false), $params, $pageSize, $initialPage, $mode, $scrollMargin);
        $this->setOption("sortMode", "remote"); // http://www.tabulator.info/docs/5.5/sort#ajax-sort
        $this->setOption("filterMode", "remote"); // http://www.tabulator.info/docs/5.5/filter#ajax-filter
        return $this;
    }

    /**
     * @link https://tabulator.info/docs/5.5/layout#responsive
     * @param boolean $startOpen
     * @param string $mode collapse|hide|flexCollapse
     * @return self
     */
    public function wizardResponsiveCollapse(bool $startOpen = false, string $mode = "collapse"): self
    {
        $this->setOption("responsiveLayout", $mode);
        $this->setOption("responsiveLayoutCollapseStartOpen", $startOpen);
        if ($mode != "hide") {
            $this->columns = array_merge([
                'ui_responsive_collapse' => [
                    "cssClass" => 'tabulator-cell-btn',
                    'formatter' => 'responsiveCollapse',
                    'headerSort' => false,
                    'width' => 40,
                ]
            ], $this->columns);
        }
        return $this;
    }

    public function wizardDataTree(bool $startExpanded = false, bool $filter = false, bool $sort = false, string $el = null): self
    {
        $this->setOption("dataTree", true);
        $this->setOption("dataTreeStartExpanded", $startExpanded);
        $this->setOption("dataTreeFilter", $filter);
        $this->setOption("dataTreeSort", $sort);
        if ($el) {
            $this->setOption("dataTreeElementColumn", $el);
        }
        return $this;
    }

    /**
     * @param array $actions An array of bulk actions, that can extend the abstract one or use the generic with callbable
     * @return self
     */
    public function wizardSelectable(array $actions = []): self
    {
        $this->columns = array_merge([
            'ui_selectable' => [
                "hozAlign" => 'center',
                "cssClass" => 'tabulator-cell-btn tabulator-cell-selector',
                'formatter' => 'rowSelection',
                'titleFormatter' => 'rowSelection',
                'width' => 40,
                'maxWidth' => 40,
                "headerSort" => false,
            ]
        ], $this->columns);
        $this->setBulkActions($actions);
        return $this;
    }

    public function wizardMoveable(string $callback = "SSTabulator.rowMoved", $field = "Sort"): self
    {
        $this->moveUrl = $this->TempLink("item/{ID}/ajaxMove", false);
        $this->setOption("movableRows", true);
        $this->addListener("rowMoved", $callback);
        $this->columns = array_merge([
            'ui_move' => [
                "hozAlign" => 'center',
                "cssClass" => 'tabulator-cell-btn tabulator-cell-selector tabulator-ui-sort',
                'rowHandle' => true,
                'formatter' => 'handle',
                'headerSort' => false,
                'frozen' => true,
                'width' => 40,
                'maxWidth' => 40,
            ],
            // We need a hidden sort column
            self::UI_SORT => [
                "field" => $field,
                'visible' => false,
            ],
        ], $this->columns);
        return $this;
    }

    /**
     * @param string $field
     * @param string $toggleElement arrow|header|false (header by default)
     * @param boolean $isBool
     * @return void
     */
    public function wizardGroupBy(string $field, string $toggleElement = 'header', bool $isBool = false)
    {
        $this->setOption("groupBy", $field);
        $this->setOption("groupToggleElement", $toggleElement);
        if ($isBool) {
            $this->setOption("groupHeader", ['_fn' => self::JS_BOOL_GROUP_HEADER]);
        }
    }

    /**
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function handleItem($request)
    {
        // Our getController could either give us a true Controller, if this is the top-level GridField.
        // It could also give us a RequestHandler in the form of (GridFieldDetailForm_ItemRequest, TabulatorGrid...)
        $requestHandler = $this->getForm()->getController();
        try {
            $record = $this->getRecordFromRequest($request);
        } catch (Exception $e) {
            return $requestHandler->httpError(404, $e->getMessage());
        }

        if (!$record) {
            return $requestHandler->httpError(404, 'That record was not found');
        }
        $handler = $this->getItemRequestHandler($record, $requestHandler);
        return $handler->handleRequest($request);
    }

    /**
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function handleTool($request)
    {
        // Our getController could either give us a true Controller, if this is the top-level GridField.
        // It could also give us a RequestHandler in the form of (GridFieldDetailForm_ItemRequest, TabulatorGrid...)
        $requestHandler = $this->getForm()->getController();
        $tool = $this->getToolFromRequest($request);
        if (!$tool) {
            return $requestHandler->httpError(404, 'That tool was not found');
        }
        return $tool->handleRequest($request);
    }

    /**
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function handleBulkAction($request)
    {
        // Our getController could either give us a true Controller, if this is the top-level GridField.
        // It could also give us a RequestHandler in the form of (GridFieldDetailForm_ItemRequest, TabulatorGrid...)
        $requestHandler = $this->getForm()->getController();
        $bulkAction = $this->getBulkActionFromRequest($request);
        if (!$bulkAction) {
            return $requestHandler->httpError(404, 'That bulk action was not found');
        }
        return $bulkAction->handleRequest($request);
    }

    /**
     * @return string name of {@see TabulatorGrid_ItemRequest} subclass
     */
    public function getItemRequestClass(): string
    {
        if ($this->itemRequestClass) {
            return $this->itemRequestClass;
        } elseif (ClassInfo::exists(static::class . '_ItemRequest')) {
            return static::class . '_ItemRequest';
        }
        return TabulatorGrid_ItemRequest::class;
    }

    /**
     * Build a request handler for the given record
     *
     * @param DataObject $record
     * @param RequestHandler $requestHandler
     * @return TabulatorGrid_ItemRequest
     */
    protected function getItemRequestHandler($record, $requestHandler)
    {
        $class = $this->getItemRequestClass();
        $assignedClass = $this->itemRequestClass;
        $this->extend('updateItemRequestClass', $class, $record, $requestHandler, $assignedClass);
        /** @var TabulatorGrid_ItemRequest $handler */
        $handler = Injector::inst()->createWithArgs(
            $class,
            [$this, $record, $requestHandler]
        );
        if ($template = $this->getTemplate()) {
            $handler->setTemplate($template);
        }
        $this->extend('updateItemRequestHandler', $handler);
        return $handler;
    }

    public function getStateKey(string $TabID = null)
    {
        $nested = [];
        $form = $this->getForm();
        $scope = $this->modelClass ? str_replace('_', '\\', $this->modelClass) :  "default";
        if ($form) {
            $controller = $form->getController();

            // We are in a nested form, track by id since each records needs it own state
            while ($controller instanceof TabulatorGrid_ItemRequest) {
                $record = $controller->getRecord();
                $nested[str_replace('_', '\\', get_class($record))] = $record->ID;

                // Move to parent controller
                $controller = $controller->getController();
            }

            // Scope by top controller class
            $scope = str_replace('_', '\\', get_class($controller));
        }

        $baseKey = 'TabulatorState';
        if ($TabID) {
            $baseKey .= '_' . $TabID;
        }
        $name = $this->getName();
        $key = "$baseKey.$scope.$name";
        foreach ($nested as $k => $v) {
            $key .= "$k.$v";
        }
        return $key;
    }

    /**
     * @param HTTPRequest|null $request
     * @return array{'page': int, 'limit': int, 'sort': array, 'filter': array}
     */
    public function getState(HTTPRequest $request = null)
    {
        if ($request === null) {
            $request = Controller::curr()->getRequest();
        }
        $TabID = $request->requestVar('TabID') ?? null;
        $stateKey = $this->getStateKey($TabID);
        $state = $request->getSession()->get($stateKey);
        return $state ?? [
            'page' => 1,
            'limit' => $this->pageSize,
            'sort' => [],
            'filter' => [],
        ];
    }

    public function setState(HTTPRequest $request, $state)
    {
        $TabID = $request->requestVar('TabID') ?? null;
        $stateKey = $this->getStateKey($TabID);
        $request->getSession()->set($stateKey, $state);
        // If we are in a new controller, we can clear other states
        // Note: this would break tabbed navigation if you try to open multiple tabs, see below for more info
        // @link https://github.com/silverstripe/silverstripe-framework/issues/9556
        $matches = [];
        preg_match_all('/\.(.*?)\./', $stateKey, $matches);
        $scope = $matches[1][0] ?? null;
        if ($scope) {
            self::clearAllStates($scope);
        }
    }

    public function clearState(HTTPRequest $request)
    {
        $TabID = $request->requestVar('TabID') ?? null;
        $stateKey = $this->getStateKey($TabID);
        $request->getSession()->clear($stateKey);
    }

    public static function clearAllStates(string $exceptScope = null, string $TabID = null)
    {
        $request = Controller::curr()->getRequest();
        $baseKey = 'TabulatorState';
        if ($TabID) {
            $baseKey .= '_' . $TabID;
        }
        $allStates = $request->getSession()->get($baseKey);
        if (!$allStates) {
            return;
        }
        foreach ($allStates as $scope => $data) {
            if ($exceptScope && $scope == $exceptScope) {
                continue;
            }
            $request->getSession()->clear("TabulatorState.$scope");
        }
    }

    public function StateValue($key, $field): ?string
    {
        $state = $this->getState();
        $arr = $state[$key] ?? [];
        foreach ($arr as $s) {
            if ($s['field'] === $field) {
                return $s['value'];
            }
        }
        return null;
    }

    /**
     * Provides autocomplete lists
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function autocomplete(HTTPRequest $request)
    {
        if ($this->isDisabled() || $this->isReadonly()) {
            return $this->httpError(403);
        }
        $SecurityID = $request->getVar('SecurityID');
        if (!SecurityToken::inst()->check($SecurityID)) {
            return $this->httpError(404, "Invalid SecurityID");
        }

        $name = $request->getVar("Column");
        $col = $this->getColumn($name);
        if (!$col) {
            return $this->httpError(403, "Invalid column");
        }

        // Don't use % term as it prevents use of indexes
        $term = $request->getVar('term') . '%';
        $term = str_replace(' ', '%', $term);

        $parts = explode(".", $name);
        if (count($parts) > 2) {
            array_pop($parts);
        }
        if (count($parts) == 2) {
            $class = $parts[0];
            $field = $parts[1];
        } elseif (count($parts) == 1) {
            $class = preg_replace("/ID$/", "", $parts[0]);
            $field = 'Title';
        } else {
            return $this->httpError(403, "Invalid field");
        }

        /** @var DataObject $sng */
        $sng = $class::singleton();
        $baseTable = $sng->baseTable();

        $searchField = null;
        $searchCandidates = [
            $field, 'Name', 'Surname', 'Email', 'ID'
        ];

        // Ensure field exists, this is really rudimentary
        $db = $class::config()->db;
        foreach ($searchCandidates as $searchCandidate) {
            if ($searchField) {
                continue;
            }
            if (isset($db[$searchCandidate])) {
                $searchField = $searchCandidate;
            }
        }
        $searchCols = [$searchField];

        // For members, do something better
        if ($baseTable == 'Member') {
            $searchField = ['FirstName', 'Surname'];
            $searchCols = ['FirstName', 'Surname', 'Email'];
        }

        if (!empty($col['editorParams']['customSearchField'])) {
            $searchField = $col['editorParams']['customSearchField'];
        }
        if (!empty($col['editorParams']['customSearchCols'])) {
            $searchCols = $col['editorParams']['customSearchCols'];
        }

        // Note: we need to use the orm, even if it's slower, to make sure any extension is properly applied
        /** @var DataList $list */
        $list = $sng::get();

        // Make sure at least one field is not null...
        $where = [];
        foreach ($searchCols as $searchCol) {
            $where[] = $searchCol . ' IS NOT NULL';
        }
        $list = $list->where($where);
        // ... and matches search term ...
        $where = [];
        foreach ($searchCols as $searchCol) {
            $where[$searchCol . ' LIKE ?'] = $term;
        }
        $list = $list->whereAny($where);

        // ... and any user set requirements
        if (!empty($col['editorParams']['where'])) {
            // Deal with in clause
            $customWhere = [];
            foreach ($col['editorParams']['where'] as $col => $param) {
                // For array, we need a IN statement with a ? for each value
                if (is_array($param)) {
                    $prepValue = [];
                    $params = [];
                    foreach ($param as $paramValue) {
                        $params[] = $paramValue;
                        $prepValue[] = "?";
                    }
                    $customWhere["$col IN (" . implode(',', $prepValue) . ")"] = $params;
                } else {
                    $customWhere["$col = ?"] = $param;
                }
            }
            $list = $list->where($customWhere);
        }

        $results = iterator_to_array($list);
        $data = [];
        foreach ($results as $record) {
            if (is_array($searchField)) {
                $labelParts = [];
                foreach ($searchField as $sf) {
                    $labelParts[] = $record->$sf;
                }
                $label = implode(" ", $labelParts);
            } else {
                $label = $record->$searchField;
            }
            $data[] = [
                'value' => $record->ID,
                'label' => $label,
            ];
        }

        $json = json_encode($data);
        $response = new HTTPResponse($json);
        $response->addHeader('Content-Type', 'application/script');
        return $response;
    }

    /**
     * @link http://www.tabulator.info/docs/5.5/page#remote-response
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function load(HTTPRequest $request)
    {
        if ($this->isDisabled() || $this->isReadonly()) {
            return $this->httpError(403);
        }
        $SecurityID = $request->getVar('SecurityID');
        if (!SecurityToken::inst()->check($SecurityID)) {
            return $this->httpError(404, "Invalid SecurityID");
        }

        $page = (int) $request->getVar('page');
        $limit = (int) $request->getVar('size');

        $sort = $request->getVar('sort');
        $filter = $request->getVar('filter');

        // Persist state to allow the ItemEditForm to display navigation
        $state = [
            'page' => $page,
            'limit' => $limit,
            'sort' => $sort,
            'filter' => $filter,
        ];
        $this->setState($request, $state);

        $offset = ($page - 1) * $limit;
        $data = $this->getManipulatedData($limit, $offset, $sort, $filter);
        $data['state'] = $state;

        $encodedData = json_encode($data);
        if (!$encodedData) {
            throw new Exception(json_last_error_msg());
        }

        $response = new HTTPResponse($encodedData);
        $response->addHeader('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @param HTTPRequest $request
     * @return DataObject|null
     */
    protected function getRecordFromRequest(HTTPRequest $request): ?DataObject
    {
        $id = $request->param('ID');
        /** @var DataObject $record */
        if (is_numeric($id)) {
            /** @var Filterable $dataList */
            $dataList = $this->getList();
            $record = $dataList->byID($id);

            if (!$record) {
                $record = DataObject::get_by_id($this->getModelClass(), $id);
                if ($record) {
                    throw new RuntimeException('This record is not accessible from the list');
                }
            }
        } else {
            $record = Injector::inst()->create($this->getModelClass());
        }
        return $record;
    }

    /**
     * @param HTTPRequest $request
     * @return AbstractTabulatorTool|null
     */
    protected function getToolFromRequest(HTTPRequest $request): ?AbstractTabulatorTool
    {
        $toolID = $request->param('ID');
        $tool = $this->getTool($toolID);
        return $tool;
    }

    /**
     * @param HTTPRequest $request
     * @return AbstractBulkAction|null
     */
    protected function getBulkActionFromRequest(HTTPRequest $request): ?AbstractBulkAction
    {
        $toolID = $request->param('ID');
        $tool = $this->getBulkAction($toolID);
        return $tool;
    }

    /**
     * Get the value of a named field  on the given record.
     *
     * Use of this method ensures that any special rules around the data for this gridfield are
     * followed.
     *
     * @param DataObject $record
     * @param string $fieldName
     *
     * @return mixed
     */
    public function getDataFieldValue($record, $fieldName)
    {
        if ($record->hasMethod('relField')) {
            return $record->relField($fieldName);
        }

        if ($record->hasMethod($fieldName)) {
            return $record->$fieldName();
        }

        return $record->$fieldName;
    }

    public function getManipulatedList(): SS_List
    {
        return $this->list;
    }

    public function getList(): SS_List
    {
        return $this->list;
    }

    public function setList(SS_List $list): self
    {
        if ($this->autoloadDataList && $list instanceof DataList) {
            $this->wizardRemotePagination();
        }
        $this->list = $list;
        return $this;
    }

    public function hasArrayList(): bool
    {
        return $this->list instanceof ArrayList;
    }

    public function getArrayList(): ArrayList
    {
        if (!$this->list instanceof ArrayList) {
            throw new RuntimeException("Value is not a ArrayList, it is a: " . get_class($this->list));
        }
        return $this->list;
    }

    public function hasDataList(): bool
    {
        return $this->list instanceof DataList;
    }

    /**
     * A properly typed on which you can call byID
     * @return ArrayList|DataList
     */
    public function getByIDList()
    {
        return $this->list;
    }

    public function hasByIDList(): bool
    {
        return $this->hasDataList() || $this->hasArrayList();
    }

    public function getDataList(): DataList
    {
        if (!$this->list instanceof DataList) {
            throw new RuntimeException("Value is not a DataList, it is a: " . get_class($this->list));
        }
        return $this->list;
    }

    public function getManipulatedData(int $limit, int $offset, array $sort = null, array $filter = null): array
    {
        if (!$this->hasDataList()) {
            $data = $this->list->toNestedArray();

            $lastRow = $this->list->count();
            $lastPage = ceil($lastRow / $limit);

            $result = [
                'last_row' => $lastRow,
                'last_page' => $lastPage,
                'data' => $data,
            ];

            return $result;
        }

        $dataList = $this->getDataList();

        $schema = DataObject::getSchema();
        $dataClass = $dataList->dataClass();

        /** @var DataObject $singleton */
        $singleton = singleton($dataClass);
        $opts = $this->getTabulatorOptions($singleton);
        $resolutionMap = [];

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
                if (str_contains($field, '.')) {
                    $parts = explode(".", $field);
                    $relationName = $parts[0];

                    // Resolve relation only once in case of multiples similar keys
                    if (!isset($resolutionMap[$relationName])) {
                        $resolutionMap[$relationName] = $singleton->relObject($relationName);
                    }
                    // Not matching anything (maybe a formatting .Nice ?)
                    $resolvedObject = $resolutionMap[$relationName] ?? null;
                    if (!$resolvedObject) {
                        continue;
                    }
                    // Maybe it's an helper method like .Nice and it's not sortable in the query
                    if (!($resolvedObject instanceof DataList) && !($resolvedObject instanceof DataObject)) {
                        $field = $parts[0];
                        continue;
                    }
                    $relatedObjectClass = get_class($resolvedObject);
                    $tableName = $schema->tableForField($relatedObjectClass, $parts[1]);
                    $baseIDColumn = $schema->sqlColumnForField($dataClass, 'ID');
                    $dataList = $dataList->leftJoin($tableName, "\"{$relationName}\".\"ID\" = {$baseIDColumn}", $relationName);
                }

                $sortSql[] = $field . ' ' . $dir;
            }
        } else {
            // If we have a sort column
            if (isset($this->columns[self::UI_SORT])) {
                $sortSql[] = $this->columns[self::UI_SORT]['field'] . ' ASC';
            }
        }
        if (!empty($sortSql)) {
            $dataList = $dataList->sort(implode(", ", $sortSql));
        }

        // Filtering is an array of field/type/value arrays
        $filters = [];
        $anyFilters = [];
        $where = [];
        $anyWhere = [];
        if ($filter) {
            $searchAliases = $opts['searchAliases'] ?? [];
            $searchAliases = array_flip($searchAliases);
            foreach ($filter as $filterValues) {
                $cols = array_keys($this->columns);
                $field = $filterValues['field'];
                if (strpos($field, '__') !== 0 && !in_array($field, $cols)) {
                    throw new Exception("Invalid filter field: $field");
                }
                // If .Nice was used
                $field = str_replace('.Nice', '', $field);

                $field = $searchAliases[$field] ?? $field;
                $value = $filterValues['value'];
                $type = $filterValues['type'];

                // Some types of fields need custom sql expressions (eg uuids)
                $fieldInstance = $singleton->dbObject($field);
                if ($fieldInstance->hasMethod('filterExpression')) {
                    $where[] = $fieldInstance->filterExpression($type, $value);
                    continue;
                }

                $rawValue = $value;

                // Strict value
                if ($value === "true") {
                    $value = true;
                } elseif ($value === "false") {
                    $value = false;
                }

                switch ($type) {
                    case "=":
                        if ($field === "__wildcard") {
                            // It's a wildcard search
                            $anyFilters = $this->createWildcardFilters($rawValue);
                        } elseif ($field === "__quickfilter") {
                            // It's a quickfilter search
                            $this->createQuickFilter($rawValue, $dataList);
                        } else {
                            $filters["$field"] = $value;
                        }
                        break;
                    case "!=":
                        $filters["$field:not"] = $value;
                        break;
                    case "like":
                        $filters["$field:PartialMatch:nocase"] = $value;
                        break;
                    case "keywords":
                        $filters["$field:PartialMatch:nocase"] = str_replace(" ", "%", $value);
                        break;
                    case "starts":
                        $filters["$field:StartsWith:nocase"] = $value;
                        break;
                    case "ends":
                        $filters["$field:EndsWith:nocase"] = $value;
                        break;
                    case "<":
                        $filters["$field:LessThan:nocase"] = $value;
                        break;
                    case "<=":
                        $filters["$field:LessThanOrEqual:nocase"] = $value;
                        break;
                    case ">":
                        $filters["$field:GreaterThan:nocase"] = $value;
                        break;
                    case ">=":
                        $filters["$field:GreaterThanOrEqual:nocase"] = $value;
                        break;
                    case "in":
                        $filters["$field"] = $value;
                        break;
                    case "regex":
                        $dataList = $dataList->filters('REGEXP ' . Convert::raw2sql($value));
                        break;
                    default:
                        throw new Exception("Invalid filter type: $type");
                }
            }
        }
        if (!empty($filters)) {
            $dataList = $dataList->filter($filters);
        }
        if (!empty($anyFilters)) {
            $dataList = $dataList->filterAny($anyFilters);
        }
        if (!empty($where)) {
            $dataList = $dataList->where(implode(' AND ', $where));
        }
        if (!empty($anyWhere)) {
            $dataList = $dataList->where(implode(' OR ', $anyWhere));
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

            // Add row class
            if ($record->hasMethod('TabulatorRowClass')) {
                $item['_class'] = $record->TabulatorRowClass();
            } elseif ($record->hasMethod('getRowClass')) {
                $item['_class'] = $record->getRowClass();
            }
            // Add row color
            if ($record->hasMethod('TabulatorRowColor')) {
                $item['_color'] = $record->TabulatorRowColor();
            }

            $nested = [];
            foreach ($this->columns as $col) {
                // UI field are skipped
                if (empty($col['field'])) {
                    continue;
                }

                $field = $col['field'];

                // Explode relations or formatters
                if (strpos($field, '.') !== false) {
                    $parts = explode('.', $field);
                    $classOrField = $parts[0];
                    $relationOrMethod = $parts[1];
                    // For relations, like Users.count
                    if ($singleton->getRelationClass($classOrField)) {
                        $nested[$classOrField][] = $relationOrMethod;
                        continue;
                    } else {
                        // For fields, like SomeValue.Nice
                        $dbObject = $record->dbObject($classOrField);
                        if ($dbObject) {
                            $item[$classOrField] = [
                                $relationOrMethod => $dbObject->$relationOrMethod()
                            ];
                            continue;
                        }
                    }
                }

                // Do not override already set fields
                if (!isset($item[$field])) {
                    $getField = 'get' . ucfirst($field);

                    if ($record->hasMethod($getField)) {
                        // Prioritize getXyz method
                        $item[$field] = $record->$getField();
                    } elseif ($record->hasMethod($field)) {
                        // Regular xyz method method
                        $item[$field] = $record->$field();
                    } else {
                        // Field
                        $item[$field] = $record->getField($field);
                    }
                }
            }
            // Fill in nested data, like Users.count
            foreach ($nested as $nestedClass => $nestedColumns) {
                /** @var DataObject $relObject */
                $relObject = $record->relObject($nestedClass);
                $nestedData = [];
                foreach ($nestedColumns as $nestedColumn) {
                    $nestedData[$nestedColumn] = $this->getDataFieldValue($relObject, $nestedColumn);
                }
                $item[$nestedClass] = $nestedData;
            }
            $data[] = $item;
        }

        $result = [
            'last_row' => $lastRow,
            'last_page' => $lastPage,
            'data' => $data,
        ];

        if (Director::isDev()) {
            $result['sql'] = $dataList->sql();
        }

        return $result;
    }

    public function QuickFiltersList()
    {
        $current = $this->StateValue('filter', '__quickfilter');
        $list = new ArrayList();
        foreach ($this->quickFilters as $k => $v) {
            $list->push([
                'Value' => $k,
                'Label' => $v['label'],
                'Selected' => $k == $current
            ]);
        }
        return $list;
    }

    protected function createQuickFilter($filter, &$list)
    {
        $qf = $this->quickFilters[$filter] ?? null;
        if (!$qf) {
            return;
        }

        $callback = $qf['callback'] ?? null;
        if (!$callback) {
            return;
        }

        $callback($list);
    }

    protected function createWildcardFilters(string $value)
    {
        $wildcardFields = $this->wildcardFields;

        // Create from model
        if (empty($wildcardFields)) {
            /** @var DataObject $singl */
            $singl = singleton($this->modelClass);
            $searchableFields = $singl->searchableFields();

            foreach ($searchableFields as $k => $v) {
                $general = $v['general'] ?? true;
                if (!$general) {
                    continue;
                }
                $wildcardFields[] = $k;
            }
        }

        // Queries can have the format s:... or e:... or =:.... or %:....
        $filter = $this->defaultFilter;
        if (strpos($value, ':') === 1) {
            $parts = explode(":", $value);
            $shortcut = array_shift($parts);
            $value = implode(":", $parts);
            switch ($shortcut) {
                case 's':
                    $filter = 'StartsWith';
                    break;
                case 'e':
                    $filter = 'EndsWith';
                    break;
                case '=':
                    $filter = 'ExactMatch';
                    break;
                case '%':
                    $filter = 'PartialMatch';
                    break;
            }
        }

        // Process value
        $baseValue = $value;
        $value = str_replace(" ", "%", $value);
        $value = str_replace(['.', '_', '-'], ' ', $value);

        // Create filters
        $anyWhere = [];
        foreach ($wildcardFields as $f) {
            if (!$value) {
                continue;
            }
            $key = $f . ":" . $filter;
            $anyWhere[$key] = $value;

            // also look on unfiltered data
            if ($value != $baseValue) {
                $anyWhere[$key] = $baseValue;
            }
        }

        return $anyWhere;
    }

    public function getModelClass(): ?string
    {
        if ($this->modelClass) {
            return $this->modelClass;
        }
        if ($this->list && $this->list instanceof DataList) {
            return $this->list->dataClass();
        }
        return null;
    }

    public function setModelClass(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        return $this;
    }


    public function getDataAttribute(string $k)
    {
        if (isset($this->dataAttributes[$k])) {
            return $this->dataAttributes[$k];
        }
        return $this->getAttribute("data-$k");
    }

    public function setDataAttribute(string $k, $v): self
    {
        $this->dataAttributes[$k] = $v;
        return $this;
    }

    public function dataAttributesHTML(): string
    {
        $parts = [];
        foreach ($this->dataAttributes as $k => $v) {
            if (!$v) {
                continue;
            }
            if (is_array($v)) {
                $v = json_encode($v);
            }
            $parts[] = "data-$k='$v'";
        }
        return implode(" ", $parts);
    }

    protected function processLink(string $url): string
    {
        // It's not necessary to process
        if ($url == '#') {
            return $url;
        }
        // It's a temporary link on the form
        if (strpos($url, 'form:') === 0) {
            return $this->Link(preg_replace('/^form:/', '', $url));
        }
        // It's a temporary link on the controller
        if (strpos($url, 'controller:') === 0) {
            return $this->ControllerLink(preg_replace('/^controller:/', '', $url));
        }
        // It's a custom protocol (mailto: etc)
        if (strpos($url, ':') !== false) {
            return $url;
        }
        return $url;
    }

    protected function processLinks(): void
    {
        // Process editor and formatter links
        foreach ($this->columns as $name => $params) {
            if (!empty($params['formatterParams']['url'])) {
                $url = $this->processLink($params['formatterParams']['url']);
                $this->columns[$name]['formatterParams']['url'] = $url;
            }
            if (!empty($params['editorParams']['url'])) {
                $url = $this->processLink($params['editorParams']['url']);
                $this->columns[$name]['editorParams']['url'] = $url;
            }
            // Set valuesURL automatically if not already set
            if (!empty($params['editorParams']['autocomplete'])) {
                if (empty($params['editorParams']['valuesURL'])) {
                    $params = [
                        'Column' => $name,
                        'SecurityID' => SecurityToken::getSecurityID(),
                    ];
                    $url = $this->Link('autocomplete') . '?' . http_build_query($params);
                    $this->columns[$name]['editorParams']['valuesURL'] = $url;
                    $this->columns[$name]['editorParams']['filterRemote'] = true;
                }
            }
        }

        // Other links
        $url = $this->getOption('ajaxURL');
        if ($url) {
            $this->setOption('ajaxURL', $this->processLink($url));
        }
    }

    /**
     * @link https://github.com/lekoala/formidable-elements/blob/master/src/classes/tabulator/Format/formatters/button.js
     */
    public function makeButton(string $urlOrAction, string $icon, string $title): array
    {
        $opts = [
            "responsive" => 0,
            "cssClass" => 'tabulator-cell-btn',
            "tooltip" => $title,
            "formatter" => "button",
            "formatterParams" => [
                "icon" => $icon,
                "title" => $title,
                "url" => $this->TempLink($urlOrAction), // On the controller by default
            ],
            "cellClick" => ["__fn" => "SSTabulator.buttonHandler"],
            // We need to force its size otherwise Tabulator will assign too much space
            "width" => 36 + strlen($title) * 12,
            "hozAlign" => "center",
            "headerSort" => false,
        ];
        return $opts;
    }

    public function addButtonFromArray(string $action, array $opts = [], string $before = null): self
    {
        // Insert before given column
        if ($before) {
            $this->addColumnBefore("action_$action", $opts, $before);
        } else {
            $this->columns["action_$action"] = $opts;
        }
        return $this;
    }

    /**
     * @param string $action Action name
     * @param string $url Parameters between {} will be interpolated by row values.
     * @param string $icon
     * @param string $title
     * @param string|null $before
     * @return self
     */
    public function addButton(string $action, string $url, string $icon, string $title, string $before = null): self
    {
        $opts = $this->makeButton($url, $icon, $title);
        $this->addButtonFromArray($action, $opts, $before);
        return $this;
    }

    public function addEditButton()
    {
        $itemUrl = $this->TempLink('item/{ID}', false);
        $this->addButton(self::UI_EDIT, $itemUrl, "edit", _t('TabulatorGrid.Edit', 'Edit'));
        $this->editUrl = $this->TempLink("item/{ID}/ajaxEdit", false);
    }

    public function moveButton(string $action, $pos = self::POS_END): self
    {
        $keep = null;
        foreach ($this->columns as $k => $v) {
            if ($k == "action_$action") {
                $keep = $this->columns[$k];
                unset($this->columns[$k]);
            }
        }
        if ($keep) {
            if ($pos == self::POS_END) {
                $this->columns["action_$action"] = $keep;
            }
            if ($pos == self::POS_START) {
                $this->columns = ["action_$action" => $keep] + $this->columns;
            }
        }
        return $this;
    }

    public function shiftButton(string $action, string $url, string $icon, string $title): self
    {
        // Find first action
        foreach ($this->columns as $name => $options) {
            if (strpos($name, 'action_') === 0) {
                return $this->addButton($action, $url, $icon, $title, $name);
            }
        }
        return $this->addButton($action, $url, $icon, $title);
    }

    public function getActions(): array
    {
        $cols = [];
        foreach ($this->columns as $name => $options) {
            if (strpos($name, 'action_') === 0) {
                $cols[$name] = $options;
            }
        }
        return $cols;
    }

    public function getUiColumns(): array
    {
        $cols = [];
        foreach ($this->columns as $name => $options) {
            if (strpos($name, 'ui_') === 0) {
                $cols[$name] = $options;
            }
        }
        return $cols;
    }

    public function getSystemColumns(): array
    {
        return array_merge($this->getActions(), $this->getUiColumns());
    }

    public function removeButton(string $action): self
    {
        if ($this->hasButton($action)) {
            unset($this->columns["action_$action"]);
        }
        return $this;
    }

    public function hasButton(string $action): bool
    {
        return isset($this->columns["action_$action"]);
    }

    /**
     * @link http://www.tabulator.info/docs/5.5/columns#definition
     * @param string $field (Required) this is the key for this column in the data array
     * @param string $title (Required) This is the title that will be displayed in the header for this column
     * @param array $opts Other options to merge in
     * @return $this
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
     * @link http://www.tabulator.info/docs/5.5/columns#definition
     * @param array $opts Other options to merge in
     * @param ?string $before
     * @return $this
     */
    public function addColumnFromArray(array $opts = [], $before = null)
    {
        if (empty($opts['field']) || !isset($opts['title'])) {
            throw new Exception("Missing field or title key");
        }
        $field = $opts['field'];

        if ($before) {
            $this->addColumnBefore($field, $opts, $before);
        } else {
            $this->columns[$field] = $opts;
        }

        return $this;
    }

    protected function addColumnBefore($field, $opts, $before)
    {
        if (array_key_exists($before, $this->columns)) {
            $new = [];
            foreach ($this->columns as $k => $value) {
                if ($k === $before) {
                    $new[$field] = $opts;
                }
                $new[$k] = $value;
            }
            $this->columns = $new;
        }
    }

    public function makeColumnEditable(string $field, string $editor = "input", array $params = [])
    {
        $col = $this->getColumn($field);
        if (!$col) {
            throw new InvalidArgumentException("$field is not a valid column");
        }

        switch ($editor) {
            case 'date':
                $editor = "input";
                $params = [
                    'mask' => "9999-99-99",
                    'maskAutoFill' => 'true',
                ];
                break;
            case 'datetime':
                $editor = "input";
                $params = [
                    'mask' => "9999-99-99 99:99:99",
                    'maskAutoFill' => 'true',
                ];
                break;
        }

        if (empty($col['cssClass'])) {
            $col['cssClass'] = 'no-change-track';
        } else {
            $col['cssClass'] .= ' no-change-track';
        }

        $col['editor'] = $editor;
        $col['editorParams'] = $params;
        if ($editor == "list") {
            if (!empty($params['autocomplete'])) {
                $col['headerFilter'] = "input"; // force input
            } else {
                $col['headerFilterParams'] = $params; // editor is used as base filter editor
            }
        }


        $this->setColumn($field, $col);
    }

    /**
     * Get column details

     * @param string $key
     */
    public function getColumn(string $key): ?array
    {
        if (isset($this->columns[$key])) {
            return $this->columns[$key];
        }
        return null;
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
     * Update column details
     *
     * @param string $key
     * @param array $col
     */
    public function updateColumn(string $key, array $col): self
    {
        $data = $this->getColumn($key);
        if ($data) {
            $this->setColumn($key, array_merge($data, $col));
        }
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
     * Remove a column
     *
     * @param array $keys
     */
    public function removeColumns(array $keys): void
    {
        foreach ($keys as $key) {
            $this->removeColumn($key);
        }
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
     */
    public function setColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function clearColumns(bool $keepSystem = true): void
    {
        $sysNames = array_keys($this->getSystemColumns());
        foreach ($this->columns as $k => $v) {
            if ($keepSystem && in_array($k, $sysNames)) {
                continue;
            }
            $this->removeColumn($k);
        }
    }

    /**
     * This should be the rough equivalent to GridFieldDataColumns::getDisplayFields
     */
    public function getDisplayFields(): array
    {
        $fields = [];
        foreach ($this->columns as $col) {
            if (empty($col['field'])) {
                continue;
            }
            $fields[$col['field']] = $col['title'];
        }
        return $fields;
    }

    /**
     * This should be the rough equivalent to GridFieldDataColumns::setDisplayFields
     */
    public function setDisplayFields(array $arr): void
    {
        $this->clearColumns();
        $actions = array_keys($this->getActions());
        $before = $actions[0] ?? null;
        foreach ($arr as $k => $v) {
            if (!$k || !$v) {
                continue;
            }
            $this->addColumnFromArray([
                'field' => $k,
                'title' => $v,
            ], $before);
        }
    }

    /**
     * Convenience method that get/set fields
     */
    public function addDisplayFields(array $arr): void
    {
        $fields = $this->getDisplayFields();
        $fields = array_merge($fields, $arr);
        $this->setDisplayFields($fields);
    }

    /**
     * @param string|AbstractTabulatorTool $tool Pass name or class
     * @return AbstractTabulatorTool|null
     */
    public function getTool($tool): ?AbstractTabulatorTool
    {
        if (is_object($tool)) {
            $tool = get_class($tool);
        }
        if (!is_string($tool)) {
            throw new InvalidArgumentException('Tool must be an object or a class name');
        }
        foreach ($this->tools as $t) {
            if ($t['name'] === $tool) {
                return $t['tool'];
            }
            if ($t['tool'] instanceof $tool) {
                return $t['tool'];
            }
        }
        return null;
    }

    /**
     * @param string $pos start|end
     * @param AbstractTabulatorTool $tool
     * @param string $name
     * @return self
     */
    public function addTool(string $pos, AbstractTabulatorTool $tool, string $name = ''): self
    {
        $tool->setTabulatorGrid($this);
        if ($tool->getName() && !$name) {
            $name = $tool->getName();
        }
        $tool->setName($name);

        $this->tools[] = [
            'position' => $pos,
            'tool' => $tool,
            'name' => $name,
        ];
        return $this;
    }

    public function addToolStart(AbstractTabulatorTool $tool, string $name = ''): self
    {
        return $this->addTool(self::POS_START, $tool, $name);
    }

    public function addToolEnd(AbstractTabulatorTool $tool, string $name = ''): self
    {
        return $this->addTool(self::POS_END, $tool, $name);
    }

    public function removeTool($toolName): self
    {
        if (is_object($toolName)) {
            $toolName = get_class($toolName);
        }
        if (!is_string($toolName)) {
            throw new InvalidArgumentException('Tool must be an object or a class name');
        }
        foreach ($this->tools as $idx => $tool) {
            if ($tool['name'] === $toolName) {
                unset($this->tools[$idx]);
            }
            if (class_exists($toolName) && $tool['tool'] instanceof $toolName) {
                unset($this->tools[$idx]);
            }
        }
        return $this;
    }

    /**
     * @param string|AbstractBulkAction $bulkAction Pass name or class
     * @return AbstractBulkAction|null
     */
    public function getBulkAction($bulkAction): ?AbstractBulkAction
    {
        if (is_object($bulkAction)) {
            $bulkAction = get_class($bulkAction);
        }
        if (!is_string($bulkAction)) {
            throw new InvalidArgumentException('BulkAction must be an object or a class name');
        }
        foreach ($this->bulkActions as $ba) {
            if ($ba->getName() == $bulkAction) {
                return $ba;
            }
            if ($ba instanceof $bulkAction) {
                return $ba;
            }
        }
        return null;
    }

    public function getBulkActions(): array
    {
        return $this->bulkActions;
    }

    /**
     * @param AbstractBulkAction[] $bulkActions
     * @return self
     */
    public function setBulkActions(array $bulkActions): self
    {
        foreach ($bulkActions as $bulkAction) {
            $bulkAction->setTabulatorGrid($this);
        }
        $this->bulkActions = $bulkActions;
        return $this;
    }

    /**
     * If you didn't before, you probably want to call wizardSelectable
     * to get the actual selection checkbox too
     *
     * @param AbstractBulkAction $handler
     * @return self
     */
    public function addBulkAction(AbstractBulkAction $handler): self
    {
        $handler->setTabulatorGrid($this);

        $this->bulkActions[] = $handler;
        return $this;
    }

    public function removeBulkAction($bulkAction): self
    {
        if (is_object($bulkAction)) {
            $bulkAction = get_class($bulkAction);
        }
        if (!is_string($bulkAction)) {
            throw new InvalidArgumentException('Bulk action must be an object or a class name');
        }
        foreach ($this->bulkActions as $idx => $ba) {
            if ($ba->getName() == $bulkAction) {
                unset($this->bulkAction[$idx]);
            }
            if ($ba instanceof $bulkAction) {
                unset($this->bulkAction[$idx]);
            }
        }
        return $this;
    }

    public function getColumnDefault(string $opt)
    {
        return $this->columnDefaults[$opt] ?? null;
    }

    public function setColumnDefault(string $opt, $value)
    {
        $this->columnDefaults[$opt] = $value;
    }

    public function getColumnDefaults(): array
    {
        return $this->columnDefaults;
    }

    public function setColumnDefaults(array $columnDefaults): self
    {
        $this->columnDefaults = $columnDefaults;
        return $this;
    }

    public function getListeners(): array
    {
        return $this->listeners;
    }

    public function setListeners(array $listeners): self
    {
        $this->listeners = $listeners;
        return $this;
    }

    public function addListener(string $event, string $functionName): self
    {
        $this->listeners[$event] = $functionName;
        return $this;
    }

    public function removeListener(string $event): self
    {
        if (isset($this->listeners[$event])) {
            unset($this->listeners[$event]);
        }
        return $this;
    }

    public function getLinksOptions(): array
    {
        return $this->linksOptions;
    }

    public function setLinksOptions(array $linksOptions): self
    {
        $this->linksOptions = $linksOptions;
        return $this;
    }

    public function registerLinkOption(string $linksOption): self
    {
        $this->linksOptions[] = $linksOption;
        return $this;
    }

    public function unregisterLinkOption(string $linksOption): self
    {
        $this->linksOptions = array_diff($this->linksOptions, [$linksOption]);
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

    /**
     * Set the value of itemRequestClass
     */
    public function setItemRequestClass(string $itemRequestClass): self
    {
        $this->itemRequestClass = $itemRequestClass;
        return $this;
    }

    /**
     * Get the value of lazyInit
     */
    public function getLazyInit(): bool
    {
        return $this->lazyInit;
    }

    /**
     * Set the value of lazyInit
     */
    public function setLazyInit(bool $lazyInit): self
    {
        $this->lazyInit = $lazyInit;
        return $this;
    }

    /**
     * Get the value of rowClickTriggersAction
     */
    public function getRowClickTriggersAction(): bool
    {
        return $this->rowClickTriggersAction;
    }

    /**
     * Set the value of rowClickTriggersAction
     */
    public function setRowClickTriggersAction(bool $rowClickTriggersAction): self
    {
        $this->rowClickTriggersAction = $rowClickTriggersAction;
        return $this;
    }

    /**
     * Get the value of controllerFunction
     */
    public function getControllerFunction(): string
    {
        if (!$this->controllerFunction) {
            return $this->getName() ?? "TabulatorGrid";
        }
        return $this->controllerFunction;
    }

    /**
     * Set the value of controllerFunction
     */
    public function setControllerFunction(string $controllerFunction): self
    {
        $this->controllerFunction = $controllerFunction;
        return $this;
    }

    /**
     * Get the value of editUrl
     */
    public function getEditUrl(): string
    {
        return $this->editUrl;
    }

    /**
     * Set the value of editUrl
     */
    public function setEditUrl(string $editUrl): self
    {
        $this->editUrl = $editUrl;
        return $this;
    }

    /**
     * Get the value of moveUrl
     */
    public function getMoveUrl(): string
    {
        return $this->moveUrl;
    }

    /**
     * Set the value of moveUrl
     */
    public function setMoveUrl(string $moveUrl): self
    {
        $this->moveUrl = $moveUrl;
        return $this;
    }

    /**
     * Get the value of bulkUrl
     */
    public function getBulkUrl(): string
    {
        return $this->bulkUrl;
    }

    /**
     * Set the value of bulkUrl
     */
    public function setBulkUrl(string $bulkUrl): self
    {
        $this->bulkUrl = $bulkUrl;
        return $this;
    }

    /**
     * Get the value of globalSearch
     */
    public function getGlobalSearch(): bool
    {
        return $this->globalSearch;
    }

    /**
     * Set the value of globalSearch
     *
     * @param bool $globalSearch
     */
    public function setGlobalSearch($globalSearch): self
    {
        $this->globalSearch = $globalSearch;
        return $this;
    }

    /**
     * Get the value of wildcardFields
     */
    public function getWildcardFields(): array
    {
        return $this->wildcardFields;
    }

    /**
     * Set the value of wildcardFields
     *
     * @param array $wildcardFields
     */
    public function setWildcardFields($wildcardFields): self
    {
        $this->wildcardFields = $wildcardFields;
        return $this;
    }

    /**
     * Get the value of quickFilters
     */
    public function getQuickFilters(): array
    {
        return $this->quickFilters;
    }

    /**
     * Pass an array with as a key, the name of the filter
     * and as a value, an array with two keys: label and callback
     *
     * For example:
     * 'myquickfilter' => [
     *   'label' => 'My Quick Filter',
     *   'callback' => function (&$list) {
     *     ...
     *   }
     * ]
     *
     * @param array $quickFilters
     */
    public function setQuickFilters($quickFilters): self
    {
        $this->quickFilters = $quickFilters;
        return $this;
    }

    /**
     * Get the value of groupLayout
     */
    public function getGroupLayout(): bool
    {
        return $this->groupLayout;
    }

    /**
     * Set the value of groupLayout
     *
     * @param bool $groupLayout
     */
    public function setGroupLayout($groupLayout): self
    {
        $this->groupLayout = $groupLayout;
        return $this;
    }

    /**
     * Get the value of enableGridManipulation
     */
    public function getEnableGridManipulation(): bool
    {
        return $this->enableGridManipulation;
    }

    /**
     * Set the value of enableGridManipulation
     *
     * @param bool $enableGridManipulation
     */
    public function setEnableGridManipulation($enableGridManipulation): self
    {
        $this->enableGridManipulation = $enableGridManipulation;
        return $this;
    }

    /**
     * Get the value of defaultFilter
     */
    public function getDefaultFilter(): string
    {
        return $this->defaultFilter;
    }

    /**
     * Set the value of defaultFilter
     *
     * @param string $defaultFilter
     */
    public function setDefaultFilter($defaultFilter): self
    {
        $this->defaultFilter = $defaultFilter;
        return $this;
    }
}
