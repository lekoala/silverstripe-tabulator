<?php

namespace LeKoala\Tabulator;

use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;

interface CompatLayerInterface
{
    public function adjustItemEditForm(TabulatorGrid_ItemRequest $itemRequest, Form $form);
    public function getFormActions(TabulatorGrid_ItemRequest $itemRequest): FieldList;
}
