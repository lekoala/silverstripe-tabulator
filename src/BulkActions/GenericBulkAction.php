<?php

namespace LeKoala\Tabulator\BulkActions;

use SilverStripe\Control\HTTPRequest;
use LeKoala\Tabulator\AbstractBulkAction;

/**
 *  Generic bulk action that can run a callable
 */
class GenericBulkAction extends AbstractBulkAction
{
    protected string $name = '';
    protected string $label = '';
    protected $callable = null;
    protected bool $xhr = true;

    public function __construct($name, $label, $callable = null)
    {
        parent::__construct();

        $this->name = $name;
        $this->label = $label;

        if ($callable) {
            $this->callable = $callable;
        }
    }

    public function getI18nLabel(): string
    {
        return $this->getLabel();
    }

    public function process(HTTPRequest $request): string
    {
        $records = $this->getRecords() ?? [];
        $i = 0;
        foreach ($records as $record) {
            if ($this->callable) {
                $this->callable($record, $this->tabulatorGrid);
            }
            $i++;
        }
        $result = _t(__CLASS__ . ".RECORDSPROCSSED", "{count} records processed", ["count" => $i]);
        return $result;
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
