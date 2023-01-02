<?php

namespace LeKoala\Tabulator;

use SilverStripe\Control\RequestHandler;

/**
 * This is the base class for tools (see TabulatorAddNewButton for sample usage)
 */
class AbstractTabulatorTool extends RequestHandler
{
    protected TabulatorGrid $tabulatorGrid;

    protected string $name;

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
        $this->name = $name;
        return $this;
    }

    public function Link($action = null)
    {
        return $this->tabulatorGrid->Link('tool/' . $this->name);
    }
}
