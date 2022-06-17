<?php

namespace LeKoala\Tabulator;

use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;

/**
 * Adjust details form to fit whatever admin ui you are using
 */
interface CompatLayerInterface
{
    public function adjustItemEditForm(TabulatorGrid_ItemRequest $itemRequest, Form $form);
    public function getFormActions(TabulatorGrid_ItemRequest $itemRequest): FieldList;
}
