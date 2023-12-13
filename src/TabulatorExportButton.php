<?php

namespace LeKoala\Tabulator;

use LeKoala\ExcelImportExport\ExcelGridFieldExportButton;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\RelationList;
use SilverStripe\View\ArrayData;

/**
 * This component provides a button for exporting data
 * Depends on excel-import-export module
 */
class TabulatorExportButton extends AbstractTabulatorTool
{
    public function __construct()
    {
        parent::__construct();
        $this->btn = new ExcelGridFieldExportButton();
        $this->btn->setIsLimited(false);
    }

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
            $this->buttonName = _t('Tabulator.ExportRecords', 'Export');
        }

        $data = new ArrayData([
            'ButtonName' => $this->buttonName,
            'ButtonClasses' => 'btn-secondary font-icon-export no-ajax',
            'Icon' => $this->isAdmini() ? 'list_alt' : '',
        ]);
        return $this->renderWith($this->getViewerTemplates(), $data);
    }

    public function getButton(): ExcelGridFieldExportButton
    {
        return $this->btn;
    }

    public function index()
    {
        return $this->btn->handleExport($this->tabulatorGrid);
    }
}
