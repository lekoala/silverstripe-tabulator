<?php

namespace LeKoala\Tabulator;

use Exception;
use RuntimeException;
use SilverStripe\i18n\i18n;
use SilverStripe\Forms\Form;
use InvalidArgumentException;
use SilverStripe\ORM\SS_List;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;
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
use SilverStripe\ORM\FieldType\DBBoolean;
use LeKoala\ModularBehaviour\ModularFormField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Core\Manifest\ModuleResourceLoader;

/**
 * This is a replacement for most GridField usages in SilverStripe
 * It can easily work in the frontend too
 *
 * @link http://www.tabulator.info/
 */
class TabulatorGrid extends ModularFormField
{
    const POS_START = 'start';
    const POS_END = 'end';

    // @link http://www.tabulator.info/examples/5.4?#fittodata
    const LAYOUT_FIT_DATA = "fitData";
    const LAYOUT_FIT_DATA_FILL = "fitDataFill";
    const LAYOUT_FIT_DATA_STRETCH = "fitDataStretch";
    const LAYOUT_FIT_DATA_TABLE = "fitDataTable";
    const LAYOUT_FIT_COLUMNS = "fitColumns";

    const RESPONSIVE_LAYOUT_HIDE = "hide";
    const RESPONSIVE_LAYOUT_COLLAPSE = "collapse";

    // @link http://www.tabulator.info/docs/5.4/format
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
    // @link http://www.tabulator.info/docs/5.4/format#format-module
    const FORMATTER_ROW_SELECTION = 'rowSelection';
    const FORMATTER_RESPONSIVE_COLLAPSE = 'responsiveCollapse';

    // our built in functions
    const JS_FLAG_FORMATTER = 'SSTabulator.flagFormatter';
    const JS_BUTTON_FORMATTER = 'SSTabulator.buttonFormatter';
    const JS_CUSTOM_TICK_CROSS_FORMATTER = 'SSTabulator.customTickCrossFormatter';
    const JS_BOOL_GROUP_HEADER = 'SSTabulator.boolGroupHeader';
    const JS_SIMPLE_ROW_FORMATTER = 'SSTabulator.simpleRowFormatter';
    const JS_EXPAND_TOOLTIP = 'SSTabulator.expandTooltip';
    const JS_DATA_AJAX_RESPONSE = 'SSTabulator.dataAjaxResponse';

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
        'tool/$ID' => 'handleTool',
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
    private static string $theme = 'bootstrap5';

    /**
     * @config
     */
    private static string $version = '5.4';

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
    private static bool $use_custom_build = true;

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
     * @link http://www.tabulator.info/docs/5.4/options
     * @config
     */
    private static array $default_options = [
        'index' => "ID", // http://tabulator.info/docs/5.4/data#row-index
        'layout' => 'fitColumns', // http://www.tabulator.info/docs/5.4/layout#layout
        'height' => '100%', // http://www.tabulator.info/docs/5.4/layout#height-fixed
        'responsiveLayout' => "hide", // http://www.tabulator.info/docs/5.4/layout#responsive
        'rowFormatter' => "SSTabulator.simpleRowFormatter", // http://tabulator.info/docs/5.4/format#row
    ];

    /**
     * @link http://tabulator.info/docs/5.4/columns#defaults
     * @config
     */
    private static array $default_column_options = [
        'resizable' => false,
        'tooltip' => 'SSTabulator.expandTooltip',
    ];

    private static bool $enable_ajax_init = true;

    /**
     * @config
     */
    private static array $custom_pagination_icons = [];

    /**
     * @config
     */
    private static bool $default_lazy_init = false;

    /**
     * Data source.
     */
    protected ?SS_List $list;

    /**
     * @link http://www.tabulator.info/docs/5.4/columns
     */
    protected array $columns = [];

    /**
     * @link http://tabulator.info/docs/5.4/columns#defaults
     */
    protected array $columnDefaults = [];

    /**
     * @link http://www.tabulator.info/docs/5.4/options
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

    protected array $jsNamespaces = [
        'SSTabulator'
    ];

    protected array $linksOptions = [
        'ajaxURL'
    ];

    protected array $dataAttributes = [];

    protected string $controllerFunction = "";

    protected bool $useConfigProvider = false;

    protected bool $useInitScript = false;

    protected string $editUrl = "";

    protected string $moveUrl = "";

    protected string $bulkUrl = "";

    /**
     * @param string $fieldName
     * @param string|null|bool $title
     * @param SS_List $value
     */
    public function __construct($name, $title = null, $value = null)
    {
        parent::__construct($name, $title, $value);
        $this->options = self::config()->default_options ?? [];
        $this->columnDefaults = self::config()->default_column_options ?? [];
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
     * Temporary link that will be replaced by a real link by processLinks
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

    public function configureFromDataObject($className = null, bool $clear = true): void
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

        // Mock some base columns using SilverStripe built-in methods
        $columns = [];
        foreach ($singl->summaryFields() as $field => $title) {
            $title = str_replace(".", " ", $title);
            $columns[$field] = [
                'field' => $field,
                'title' => $title,
            ];

            $dbObject = $singl->dbObject($field);
            if ($dbObject) {
                if ($dbObject instanceof DBBoolean) {
                    $columns[$field]['formatter'] = "SSTabulator.customTickCrossFormatter";
                }
            }
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
        if ($singl->hasField('Sort')) {
            $this->wizardMoveable();
        }

        // Actions
        // We use a pseudo link, because maybe we cannot call Link() yet if it's not linked to a form

        $this->bulkUrl = $this->TempLink("bulkAction/", false);

        // - Core actions, handled by TabulatorGrid
        $itemUrl = $this->TempLink('item/{ID}', false);
        if ($singl->canEdit()) {
            $this->addButton("ui_edit", $itemUrl, "edit", "Edit");
            $this->editUrl = $this->TempLink("item/{ID}/ajaxEdit", false);
        } elseif ($singl->canView()) {
            $this->addButton("ui_view", $itemUrl, "visibility", "View");
        }

        // - Tools
        $this->tools = [];
        if ($singl->canCreate()) {
            $this->addTool(self::POS_START, new TabulatorAddNewButton($this));
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
        $use_cdn = self::config()->use_cdn;
        $use_custom_build = self::config()->use_custom_build;
        $theme = self::config()->theme; // simple, midnight, modern or framework
        $version = self::config()->version;
        $luxon_version = self::config()->luxon_version;
        $enable_luxon = self::config()->enable_luxon;
        $last_icon_version = self::config()->last_icon_version;
        $enable_last_icon = self::config()->enable_last_icon;
        $enable_js_modules = self::config()->enable_js_modules;

        $jsOpts = [];
        if ($enable_js_modules) {
            $jsOpts['type'] = 'module';
        }

        if ($use_cdn) {
            $baseDir = "https://cdn.jsdelivr.net/npm/tabulator-tables@$version/dist";
        } else {
            $asset = ModuleResourceLoader::resourceURL('lekoala/silverstripe-tabulator:client/cdn/js/tabulator.min.js');
            $baseDir = dirname(dirname($asset));
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
        if ($use_custom_build) {
            // if (Director::isDev() && !Director::is_ajax()) {
            //     Requirements::javascript("lekoala/silverstripe-tabulator:client/custom-tabulator.js");
            // } else {
            Requirements::javascript("lekoala/silverstripe-tabulator:client/custom-tabulator.min.js", $jsOpts);
            // }
            Requirements::javascript('lekoala/silverstripe-tabulator:client/TabulatorField.js', $jsOpts);
        } else {
            Requirements::javascript("$baseDir/js/tabulator.min.js", $jsOpts);
            Requirements::javascript('lekoala/silverstripe-tabulator:client/TabulatorField.js', $jsOpts);
        }

        if ($theme) {
            Requirements::css("$baseDir/css/tabulator_$theme.min.css");
        } else {
            Requirements::css("$baseDir/css/tabulator.min.css");
        }

        if ($theme && $theme == "bootstrap5") {
            Requirements::css('lekoala/silverstripe-tabulator:client/custom-tabulator.min.css');
        }
    }

    public function setValue($value, $data = null)
    {
        if ($value instanceof DataList) {
            $this->configureFromDataObject($value->dataClass());
        }
        return parent::setValue($value, $data);
    }

    public function getModularName()
    {
        return 'SSTabulator.createTabulator';
    }

    public function getModularSelector()
    {
        return '.tabulatorgrid';
    }

    public function getModularConfigName()
    {
        return str_replace('-', '_', $this->ID()) . '_config';
    }

    public function getModularConfig()
    {
        $JsonOptions = $this->JsonOptions();
        $configName = $this->getModularConfigName();
        $script = "var $configName = $JsonOptions";
        return $script;
    }

    public function Field($properties = [])
    {
        $this->addExtraClass(self::config()->theme);
        if ($this->lazyInit) {
            $this->setModularLazy($this->lazyInit);
        }
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
        $customIcons = self::config()->custom_pagination_icons;
        $this->setDataAttribute("use-custom-pagination-icons", empty($customIcons));

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

        if ($this->useConfigProvider) {
            $configLink = "/" . ltrim($this->Link("configProvider"), "/");
            $configLink .= "?t=" . time();
            // This cannot be loaded as a js module
            Requirements::javascript($configLink, ['type' => 'application/javascript', 'defer' => 'true']);
        } elseif ($this->useInitScript) {
            Requirements::customScript($this->getInitScript());
        }

        // Skip modular behaviour
        if ($this->useConfigProvider || $this->useInitScript) {
            return FormField::Field($properties);
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
        // This will always default to last icon if present
        $customIcons = self::config()->custom_pagination_icons;
        if (!empty($customIcons)) {
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

        $format = Director::isDev() ? JSON_PRETTY_PRINT : 0;
        $json = json_encode($opts, $format);

        // Escape functions by namespace (see TabulatorField.js)
        foreach ($this->jsNamespaces as $ns) {
            $json = preg_replace('/"(' . $ns . '\.[a-zA-Z]*)"/', "$1", $json);
            // Keep static namespaces
            $json = str_replace("*" . $ns, $ns, $json);
        }

        return $json;
    }

    /**
     * @param Controller $controller
     * @return CompatLayerInterface
     */
    public function getCompatLayer(Controller $controller)
    {
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

    public function makeHeadersSticky(): self
    {
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
            $this->setOption("ajaxResponse", self::JS_DATA_AJAX_RESPONSE);
        }
        return $this;
    }

    /**
     * @link http://www.tabulator.info/docs/5.4/page#remote
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
        }
        $this->setOption("paginationSize", $pageSize);
        $this->setOption("paginationInitialPage", $initialPage);
        $this->setOption("paginationCounter", 'rows'); // http://www.tabulator.info/docs/5.4/page#counter
        return $this;
    }

    public function wizardRemotePagination(int $pageSize = 0, int $initialPage = 1, array $params = []): self
    {
        $this->setRemotePagination($this->TempLink('load', false), $params, $pageSize, $initialPage);
        $this->setOption("sortMode", "remote"); // http://www.tabulator.info/docs/5.4/sort#ajax-sort
        $this->setOption("filterMode", "remote"); // http://www.tabulator.info/docs/5.4/filter#ajax-filter
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
        }
        $this->setOption("paginationSize", $pageSize);
        $this->setOption("paginationInitialPage", $initialPage);
        $this->setOption("paginationCounter", 'rows'); // http://www.tabulator.info/docs/5.4/page#counter
        return $this;
    }

    public function wizardProgressiveLoad(int $pageSize = 0, int $initialPage = 1, string $mode = 'scroll', int $scrollMargin = 0, array $extraParams = []): self
    {
        $params = array_merge([
            'SecurityID' => SecurityToken::getSecurityID()
        ], $extraParams);
        $this->setProgressiveLoad($this->TempLink('load', false), $params, $pageSize, $initialPage, $mode, $scrollMargin);
        $this->setOption("sortMode", "remote"); // http://www.tabulator.info/docs/5.4/sort#ajax-sort
        $this->setOption("filterMode", "remote"); // http://www.tabulator.info/docs/5.4/filter#ajax-filter
        return $this;
    }

    public function wizardResponsiveCollapse(bool $startOpen = false, string $mode = "collapse"): self
    {
        $this->setOption("responsiveLayout", $mode);
        $this->setOption("responsiveLayoutCollapseStartOpen", $startOpen);
        $this->columns = array_merge([
            'ui_responsive_collapse' => [
                "cssClass" => 'tabulator-cell-btn',
                'formatter' => 'responsiveCollapse',
                'headerSort' => false,
                'width' => 40,
            ]
        ], $this->columns);
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

    public function wizardSelectable(array $actions = []): self
    {
        $this->columns = array_merge([
            'ui_selectable' => [
                "hozAlign" => 'center',
                "cssClass" => 'tabulator-cell-btn tabulator-cell-selector',
                'formatter' => 'rowSelection',
                'titleFormatter' => 'rowSelection',
                'headerSort' => false,
                'width' => 40,
                'cellClick' => 'SSTabulator.forwardClick',
            ]
        ], $this->columns);
        $this->setBulkActions($actions);
        return $this;
    }

    public function wizardMoveable(string $callback = "SSTabulator.rowMoved"): self
    {
        $this->moveUrl = $this->TempLink("item/{ID}/ajaxMove", false);
        $this->setOption("movableRows", true);
        $this->addListener("rowMoved", $callback);
        $this->columns = array_merge([
            'ui_move' => [
                "hozAlign" => 'center',
                "cssClass" => 'tabulator-cell-btn tabulator-cell-selector',
                'rowHandle' => true,
                'formatter' => 'handle',
                'headerSort' => false,
                'frozen' => true,
                'width' => 40,
            ]
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
            $this->setOption("groupHeader", self::JS_BOOL_GROUP_HEADER);
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
        $record = $this->getRecordFromRequest($request);
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

    public function getStateKey()
    {
        return $this->getName();
    }

    public function getState(HTTPRequest $request)
    {
        $stateKey = $this->getName();
        $state = $request->getSession()->get("TabulatorState[$stateKey]");
        return $state ?? [
            'page' => 1,
            'limit' => $this->pageSize,
            'sort' => [],
            'filter' => [],
        ];
    }

    public function setState(HTTPRequest $request, $state)
    {
        $stateKey = $this->getName();
        $request->getSession()->set("TabulatorState[$stateKey]", $state);
    }

    /**
     * Deprecated in favor of modular behaviour
     * @deprecated
     * @return string
     */
    public function getInitScript(): string
    {
        $JsonOptions = $this->JsonOptions();
        $ID = $this->ID();
        $script = "SSTabulator.init(\"#$ID\", $JsonOptions);";
        return $script;
    }

    /**
     * Provides the configuration for this instance
     *
     * This is really useful in the context of the admin as it will be served over
     * ajax
     *
     * Deprecated in favor of modular behaviour
     * @deprecated
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function configProvider(HTTPRequest $request)
    {
        if (!$this->useConfigProvider) {
            return $this->httpError(404);
        }
        $response = new HTTPResponse($this->getInitScript());
        $response->addHeader('Content-Type', 'application/script');
        return $response;
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
     * @link http://www.tabulator.info/docs/5.4/page#remote-response
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

        $response = new HTTPResponse(json_encode($data));
        $response->addHeader('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @param HTTPRequest $request
     * @return DataObject|null
     */
    protected function getRecordFromRequest(HTTPRequest $request): ?DataObject
    {
        /** @var DataObject $record */
        if (is_numeric($request->param('ID'))) {
            /** @var Filterable $dataList */
            $dataList = $this->getList();
            $record = $dataList->byID($request->param('ID'));
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

                // Strict value
                if ($value === "true") {
                    $value = true;
                } elseif ($value === "false") {
                    $value = false;
                }

                switch ($type) {
                    case "=":
                        $where["$field"] = $value;
                        break;
                    case "!=":
                        $where["$field:not"] = $value;
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
                $field = $col['field'] ?? ''; // actions don't have field
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

        $result = [
            'last_row' => $lastRow,
            'last_page' => $lastPage,
            'data' => $data,
        ];

        return $result;
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

    public function makeButton(string $urlOrAction, string $icon, string $title): array
    {
        $opts = [
            "responsive" => 0,
            "cssClass" => 'tabulator-cell-btn',
            "tooltip" => $title,
            "formatter" => "SSTabulator.buttonFormatter",
            "formatterParams" => [
                "icon" => $icon,
                "title" => $title,
                "url" => $this->TempLink($urlOrAction), // On the controller by default
            ],
            "cellClick" => "SSTabulator.buttonHandler",
            "width" => 70,
            "hozAlign" => "center",
            "headerSort" => false,

        ];
        return $opts;
    }

    public function addButtonFromArray(string $action, array $opts = [], string $before = null): self
    {
        // Insert before given column
        if ($before) {
            if (array_key_exists($before, $this->columns)) {
                $new = [];
                foreach ($this->columns as $k => $value) {
                    if ($k === $before) {
                        $new["action_$action"] = $opts;
                    }
                    $new[$k] = $value;
                }
                $this->columns = $new;
            }
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

    public function removeButton(string $action): self
    {
        if (isset($this->columns["action_$action"])) {
            unset($this->columns["action_$action"]);
        }
        return $this;
    }

    /**
     * @link http://www.tabulator.info/docs/5.4/columns#definition
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
     */
    public function setColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
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

    public function addTool(string $pos, AbstractTabulatorTool $tool, string $name = ''): self
    {
        $tool->setTabulatorGrid($this);

        $this->tools[] = [
            'position' => $pos,
            'tool' => $tool,
            'name' => $name,
        ];
        return $this;
    }

    public function removeTool($tool): self
    {
        if (is_object($tool)) {
            $tool = get_class($tool);
        }
        if (!is_string($tool)) {
            throw new InvalidArgumentException('Tool must be an object or a class name');
        }
        foreach ($this->tools as $idx => $tool) {
            if ($tool['name'] === $tool) {
                unset($this->tools[$idx]);
            }
            if ($tool['tool'] instanceof $tool) {
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

    public function getJsNamespaces(): array
    {
        return $this->jsNamespaces;
    }

    public function setJsNamespaces(array $jsNamespaces): self
    {
        $this->jsNamespaces = $jsNamespaces;
        return $this;
    }

    public function registerJsNamespace(string $ns): self
    {
        $this->jsNamespaces[] = $ns;
        return $this;
    }

    public function unregisterJsNamespace(string $ns): self
    {
        $this->jsNamespaces = array_diff($this->jsNamespaces, [$ns]);
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
     * Get the value of useConfigProvider
     */
    public function getUseConfigProvider(): bool
    {
        return $this->useConfigProvider;
    }

    /**
     * Set the value of useConfigProvider
     */
    public function setUseConfigProvider(bool $useConfigProvider): self
    {
        $this->useConfigProvider = $useConfigProvider;
        return $this;
    }

    /**
     * Get the value of useInitScript
     */
    public function getUseInitScript(): bool
    {
        return $this->useConfigProvider;
    }

    /**
     * Set the value of useInitScript
     */
    public function setUseInitScript(bool $useInitScript): self
    {
        $this->useInitScript = $useInitScript;
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
}
