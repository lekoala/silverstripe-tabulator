<?php

namespace LeKoala\Tabulator;

use InvalidArgumentException;
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
    protected $exportFormat = 'xlsx';
    protected $btn;

    public function __construct()
    {
        parent::__construct();
        $this->btn = new ExcelGridFieldExportButton();
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
            $this->buttonName = _t('Tabulator.ExportRecordsIn', 'Export in {format}', ['format' => $this->exportFormat]);
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
        $this->btn->setIsLimited(false);
        $this->btn->setExportType($this->exportFormat);

        return $this->btn->handleExport($this->tabulatorGrid);
    }

    /**
     * Get the value of exportFormat
     */
    public function getExportFormat(): mixed
    {
        return $this->exportFormat;
    }

    /**
     * Set the value of exportFormat
     *
     * @param mixed $exportFormat csv,xlsx
     */
    public function setExportFormat($exportFormat): self
    {
        if (!in_array($exportFormat, ['csv', 'xslx'])) {
            throw new InvalidArgumentException("Format must be csv or xlsx");
        }
        $this->exportFormat = $exportFormat;
        return $this;
    }
}
