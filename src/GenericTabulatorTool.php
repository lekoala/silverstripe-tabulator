<?php

namespace LeKoala\Tabulator;

use SilverStripe\View\ArrayData;

/**
 * Generic tool with a callable
 */
class GenericTabulatorTool extends AbstractTabulatorTool
{
    protected $callable = null;
    protected string $name = '';
    protected string $label = '';
    protected string $icon = '';
    protected string $buttonType = 'secondary';

    public function __construct($name, $label, $callable = null, $icon = '')
    {
        parent::__construct();

        $this->name = $name;
        $this->label = $label;
        $this->icon = $icon;
        $this->callable = $callable;
    }

    public function forTemplate()
    {
        $fontIcon = '';
        if ($this->icon) {
            $fontIcon = ' font-icon-' . $this->icon;
        }
        $data = new ArrayData([
            'ButtonName' => $this->label,
            'ButtonClasses' => 'btn-' . $this->buttonType . $fontIcon,
            'Icon' => $this->isAdmini() ? $this->icon : '',
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

    /**
     * Get the value of icon
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Set the value of icon
     *
     * @param string $icon
     */
    public function setIcon($icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * Get the value of buttonType
     */
    public function getButtonType(): string
    {
        return $this->buttonType;
    }

    /**
     * Set the value of buttonType
     *
     * @param string $buttonType
     */
    public function setButtonType($buttonType): self
    {
        $this->buttonType = $buttonType;
        return $this;
    }
}
