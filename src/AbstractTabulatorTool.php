<?php

namespace LeKoala\Tabulator;

use SilverStripe\Control\Controller;
use SilverStripe\Control\RequestHandler;

/**
 * This is the base class for tools (see TabulatorAddNewButton for sample usage)
 * For tools handling requests, implement index method (see TabulatorExportButton)
 */
class AbstractTabulatorTool extends RequestHandler
{
    protected TabulatorGrid $tabulatorGrid;

    protected string $buttonName = '';
    protected string $name = '';
    protected string $link = '';
    protected bool $newWindow = false;

    /**
     * Get the value of tabulatorGrid
     */
    public function getTabulatorGrid(): TabulatorGrid
    {
        return $this->tabulatorGrid;
    }

    /**
     * Set the value of tabulatorGrid
     */
    public function setTabulatorGrid(TabulatorGrid $tabulatorGrid): self
    {
        $this->tabulatorGrid = $tabulatorGrid;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName($name): self
    {
        // Don't overwrite given name with empty val
        if ($name) {
            $this->name = $name;
        }
        return $this;
    }

    public function Link($action = null)
    {
        if (!$this->link) {
            return Controller::join_links($this->tabulatorGrid->Link('tool/' . $this->name), $action);
        }
        return $this->link;
    }

    public function isAdmini()
    {
        return $this->tabulatorGrid->getCompatLayer() instanceof AdminiCompat;
    }

    /**
     * Get the value of newWindow
     */
    public function getNewWindow(): bool
    {
        return $this->newWindow;
    }

    /**
     * Set the value of newWindow
     *
     * @param bool $newWindow
     */
    public function setNewWindow($newWindow): self
    {
        $this->newWindow = $newWindow;
        return $this;
    }

    /**
     * Get the value of buttonName
     */
    public function getButtonName(): string
    {
        return $this->buttonName;
    }

    /**
     * Set the value of buttonName
     *
     * @param string $buttonName
     */
    public function setButtonName($buttonName): self
    {
        $this->buttonName = $buttonName;
        return $this;
    }
}
