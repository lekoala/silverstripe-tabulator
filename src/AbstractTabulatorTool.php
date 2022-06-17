<?php

namespace LeKoala\Tabulator;

use SilverStripe\Control\RequestHandler;

/**
 * This is the base class for tools (see TabulatorAddNewButton for sample usage)
 */
class AbstractTabulatorTool extends RequestHandler
{
    protected TabulatorGrid $tabulatorGrid;

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
}
