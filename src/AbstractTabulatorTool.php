<?php

namespace LeKoala\Tabulator;

use SilverStripe\View\ViewableData;

class AbstractTabulatorTool extends ViewableData
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
