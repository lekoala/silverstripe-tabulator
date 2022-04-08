<?php

namespace LeKoala\Tabulator;

use Exception;
use BadMethodCallException;
use RuntimeException;
use SilverStripe\Control\Controller;
use SilverStripe\i18n\i18n;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FormField;
use SilverStripe\View\Requirements;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Core\Manifest\ModuleResourceLoader;
use SilverStripe\ORM\ArrayLib;

/**
 * @link http://www.tabulator.info/
 */
class TabulatorGrid extends FormField
{
    // @link http://www.tabulator.info/examples/5.1?#fittodata
    const LAYOUT_FIT_DATA = "fitData";
    const LAYOUT_FIT_DATA_FILL = "fitDataFill";
    const LAYOUT_FIT_DATA_STRETCH = "fitDataStretch";
    const LAYOUT_FIT_DATA_TABLE = "fitDataTable";
    const LAYOUT_FIT_COLUMNS = "fitColumns";

    const RESPONSIVE_LAYOUT_HIDE = "hide";
    const RESPONSIVE_LAYOUT_COLLAPSE = "collapse";

    // @link http://www.tabulator.info/docs/5.1/format
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
    // @link http://www.tabulator.info/docs/5.1/format#format-module
    const FORMATTER_ROW_SELECTION = 'rowSelection';
    const FORMATTER_RESPONSIVE_COLLAPSE = 'responsiveCollapse';

    /**
     * @config
     */
    private static array $allowed_actions = [
        'load',
        'customAction',
        'editForm',
    ];

    private static $url_handlers = [
        '$Action//$CustomAction/$ID' => '$Action',
    ];

    private static array $casting = [
        'JsonOptions' => 'HTMLFragment',
    ];

    /**
     * @config
     */
    private static string $theme = 'bootstrap4';

    /**
     * @config
     */
    private static string $version = '5.1.8';

    /**
     * @config
     */
    private static string $luxon_version = '2.3.1';

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
    private static bool $enable_requirements = true;

    /**
     * @link http://www.tabulator.info/docs/5.1/options
     * @config
     */
    private static array $default_options = [
        'index' => "ID", // http://tabulator.info/docs/5.1/data#row-index
        'layout' => 'fitColumns', // http://www.tabulator.info/docs/5.1/layout#layout
        'height' => '100%', // http://www.tabulator.info/docs/5.1/layout#height-fixed
        'maxHeight' => "100%",
        'responsiveLayout' => "hide", // http://www.tabulator.info/docs/5.1/layout#responsive
    ];

    /**
     * @config
     */
    private static bool $use_pagination_icons = true;

    /**
     * @config
     */
    private static array $custom_pagination_icons = [
        'first' => '<l-i name="first_page"></l-i>',
        'last' => '<l-i name="last_page"></l-i>',
        'next' => '<l-i name="navigate_next"></l-i>',
        'prev' => '<l-i name="navigate_before"></l-i>',
    ];

    /**
     * @link http://www.tabulator.info/docs/5.1/columns
     */
    protected array $columns = [];

    /**
     * @link http://www.tabulator.info/docs/5.1/options
     */
    protected array $options = [];

    /**
     * Make all columns editable
     * @link http://www.tabulator.info/docs/5.1/edit
     */
    protected bool $columnsEditable = false;

    /**
     * Make all columns filterable
     * @link http://www.tabulator.info/docs/5.1/filter#header
     */
    protected bool $columnsFilterable = false;

    protected int $pageSize = 10;

    protected string $modelClass = '';

    public function __construct($name, $title = null, $value = null)
    {
        parent::__construct($name, $title, $value);
        $this->options = self::config()->default_options ?? [];
    }

    public function configureFromDataObject($className = null): void
    {
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
        if ($singl->hasMethod('tabulatorRowActions')) {
            $rowActions = $singl->tabulatorRowActions();
            if (!is_array($rowActions)) {
                throw new RuntimeException("tabulatorRowActions must return an array");
            }
            foreach ($rowActions as $key => $actionConfig) {
                $url = $this->Link('customAction') . '/' . $actionConfig['action'] . '/{ID}';
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

        if ($use_cdn) {
            $baseDir = "https://cdn.jsdelivr.net/npm/tabulator-tables@$version/dist";
        } else {
            $asset = ModuleResourceLoader::resourceURL('lekoala/silverstripe-tabulator:client/cdn/tabulator.min.js');
            $baseDir = dirname($asset);
        }

        if ($luxon_version && $enable_luxon) {
            Requirements::javascript("https://cdn.jsdelivr.net/npm/luxon@$luxon_version/build/global/luxon.min.js");
        }
        Requirements::javascript("$baseDir/js/tabulator.min.js");
        Requirements::css("$baseDir/css/tabulator.min.css");
        if ($theme) {
            Requirements::css("$baseDir/css/tabulator_$theme.min.css");
        }
        Requirements::javascript('lekoala/silverstripe-tabulator:client/TabulatorField.js');
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
        $data = $this->value ?? [];
        if ($data instanceof DataList) {
            $data = null;
            $this->setRemotePagination($this->Link("load"), [
                'SecurityID' => SecurityToken::getSecurityID()
            ]);
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

        if ($data) {
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

    public function getOption(string $k): mixed
    {
        return $this->options[$k] ?? null;
    }

    public function setOption(string $k, $v): self
    {
        $this->options[$k] = $v;
        return $this;
    }

    /**
     * @link http://www.tabulator.info/docs/5.1/page#remote
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
        $this->setOption("paginationCounter", 'rows'); // http://www.tabulator.info/docs/5.1/page#counter

        $this->setOption("sortMode", "remote"); // http://www.tabulator.info/docs/5.1/sort#ajax-sort
        $this->setOption("filterMode", "remote"); // http://www.tabulator.info/docs/5.1/filter#ajax-filter
        return $this;
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
        $controller = $this->form->getController();
        $target = $this->form;
        if ($controller->hasMethod('sessionMessage')) {
            $target = $controller;
        }
        $target->sessionMessage($result, $error ? "bad" : "good");

        return $controller->redirectBack();
    }

    /**
     * @link http://www.tabulator.info/docs/5.1/page#remote-response
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

    public function addButton(string $action, string $icon, string $title, string $before = null): self
    {
        $url = $action;
        if (strpos($url, $this->Link()) === false) {
            $controller = $this->form ? $this->form->getController() : Controller::curr();
            $url = $controller->Link($action);
        }

        $baseOpts = [
            "tooltip" => $title,
            "formatter" => "SSTabulator.buttonFormatter",
            "formatterParams" => [
                "icon" => $icon,
                "url" => $url,
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
     * @link http://www.tabulator.info/docs/5.1/columns#definition
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
}
