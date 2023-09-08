<?php

namespace LeKoala\Tabulator;

use Exception;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\RelationList;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use LeKoala\FormElements\BsAutocompleteField;

/**
 * This component provides a autocomplete field to link element to the grid
 */
class TabulatorAddExistingAutocompleter extends AbstractTabulatorTool
{
    /**
     * @config
     */
    private static array $allowed_actions = [
        'add',
        'autocomplete',
    ];


    protected string $name = 'add_existing';
    protected ?BsAutocompleteField $autocompleteField = null;

    public function forTemplate()
    {
        $grid = $this->tabulatorGrid;
        $singleton = singleton($grid->getModelClass());
        $context = [];
        if ($grid->getList() instanceof RelationList) {
            $record = $grid->getForm()->getRecord();
            if ($record && $record instanceof DataObject) {
                $context['Parent'] = $record;
            }
        }

        if (!$singleton->canCreate(null, $context)) {
            return false;
        }

        if (!$this->buttonName) {
            // provide a default button name, can be changed by calling {@link setButtonName()} on this component
            $this->buttonName = _t('SilverStripe\\Forms\\GridField\\GridField.LinkExisting', "Link Existing");
        }

        $data = new ArrayData([
            'ButtonName' => $this->buttonName,
            'ButtonClasses' => 'btn-outline-secondary font-icon-link',
            'Icon' => $this->isAdmini() ? 'link' : '',
            'AutocompleteField' => $this->getAutocompleteField(),
        ]);
        return $this->renderWith($this->getViewerTemplates(), $data);
    }

    public function getAutocompleteField(): BsAutocompleteField
    {
        if (!$this->autocompleteField) {
            $this->autocompleteField = $this->createAutocompleteField();
        }
        return $this->autocompleteField;
    }

    protected function createAutocompleteField(): BsAutocompleteField
    {
        $grid = $this->tabulatorGrid;

        $field = new BsAutocompleteField('autocomplete', _t('SilverStripe\\Forms\\GridField\\GridField.Find', "Find"));
        $field->setForm($grid->getForm());
        $field->setPlaceholder(_t('SilverStripe\\Forms\\GridField\\GridField.Find', "Find"));
        $field->setAjax(Controller::join_links($grid->Link('tool'), $this->name, 'autocomplete'));
        $field->setAjaxWizard($grid->getModelClass());
        return $field;
    }

    public function add(HTTPRequest $request): HTTPResponse
    {
        $response = new HTTPResponse();

        try {
            $body = json_encode([
                'success' => true,
            ]);
            $response->setBody($body);
            $response->addHeader('Content-Type', 'application/json');
        } catch (Exception $ex) {
            $response->setStatusCode(500);
            $response->setBody($ex->getMessage());
        }

        return $response;
    }

    public function autocomplete(HTTPRequest $request): HTTPResponse
    {
        // delegate to field
        $acField = $this->getAutocompleteField();

        try {
            $response = $acField->autocomplete($request);
        } catch (Exception $ex) {
            $response = new HTTPResponse();
            $response->setStatusCode(500);
            $response->setBody($ex->getMessage());
        }

        return $response;
    }
}
