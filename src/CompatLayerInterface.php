<?php

namespace LeKoala\Tabulator;

use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;

/**
 * Adjust details form to fit whatever admin ui you are using
 */
interface CompatLayerInterface
{
    /**
     * Allows splitting forms into the top parts with tabs, the fields and the bottom part actions
     * through the templating system
     *
     * @param TabulatorGrid_ItemRequest $itemRequest
     * @param Form $form
     * @return void
     */
    public function adjustItemEditForm(TabulatorGrid_ItemRequest $itemRequest, Form $form);

    /**
     * Provides default action for the records. Doesn't use getCMSActions but this will work
     * nicely with cms-actions module
     *
     * @param TabulatorGrid_ItemRequest $itemRequest
     * @return FieldList
     */
    public function getFormActions(TabulatorGrid_ItemRequest $itemRequest): FieldList;
}
