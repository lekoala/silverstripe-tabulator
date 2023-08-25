<?php

namespace LeKoala\Tabulator;

use LeKoala\ExcelImportExport\ExcelGridFieldExportButton;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\RelationList;
use SilverStripe\View\ArrayData;

/**
 * Generic tool with a callable
 */
class GenericTabulatorTool extends AbstractTabulatorTool
{
    protected $callable = null;
    protected string $name = '';
    protected string $label = '';

    public function __construct($name, $label, $callable = null)
    {
        parent::__construct();

        $this->name = $name;
        $this->label = $label;

        if ($callable) {
            $this->callable = $callable;
        }
    }

    public function forTemplate()
    {
        $data = new ArrayData([
            'ButtonName' => $this->label,
            'ButtonClasses' => 'btn-secondary',
            'Icon' => $this->isAdmini() ? 'list_alt' : '',
        ]);
        return $this->renderWith($this->getViewerTemplates(), $data);
    }

    public function index()
    {
        $callable = $this->callable;
        return $callable($this->tabulatorGrid);
    }

    /**
     * Get the value of callable
     */
    public function getCallable()
    {
        return $this->callable;
    }

    /**
     * Set the value of callable
     *
     * @param callable $callable
     */
    public function setCallable($callable): self
    {
        $this->callable = $callable;
        return $this;
    }
}
