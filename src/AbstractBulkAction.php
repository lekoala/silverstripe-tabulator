<?php

namespace LeKoala\Tabulator;

use Exception;
use SilverStripe\ORM\DataList;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestHandler;

/**
 * Base class to extend for all custom bulk action handlers
 */
class AbstractBulkAction extends AbstractTabulatorTool
{
    /**
     * Internal name for this action
     */
    protected string $name = '';

    /**
     * Front-end label for this handler's action
     */
    protected string $label = 'Action';

    /**
     * Whether this handler should be called via an XHR from the front-end
     */
    protected bool $xhr = true;

    /**
     * Set to true is this handler will destroy any data.
     * A warning and confirmation will be shown on the front-end.
     */
    protected bool $destructive = false;

    /**
     * Reload after action
     */
    protected bool $reload = true;

    /**
     * refresh page after action
     */
    protected bool $refresh = false;

    /**
     * Return front-end configuration
     */
    public function getConfig(): array
    {
        $config = array(
            'name' => $this->getName(),
            'label' => $this->getI18nLabel(),
            'xhr' => $this->getXhr(),
            'destructive' => $this->getDestructive()
        );
        return $config;
    }

    /**
     * Set if handler performs destructive actions
     */
    public function setDestructive(bool $destructive): self
    {
        $this->destructive = $destructive;
        return $this;
    }

    /**
     * True if the  handler performs destructive actions
     */
    public function getDestructive(): bool
    {
        return $this->destructive;
    }

    /**
     * Set if handler is called via XHR
     */

    public function setXhr(bool $xhr): self
    {
        $this->xhr = $xhr;
        return $this;
    }

    /**
     * True if handler is called via XHR
     */
    public function getXhr(): bool
    {
        return $this->xhr;
    }

    /**
     * True if reload after action
     */
    public function setReload(bool $reload): self
    {
        $this->reload = $reload;
        return $this;
    }

    /**
     * Return reload
     */
    public function getReload(): bool
    {
        return $this->reload;
    }

    /**
     * True if refresh after action
     */
    public function setRefresh(bool $refresh): self
    {
        $this->refresh = $refresh;
        return $this;
    }

    /**
     * Return refresh
     */
    public function getRefresh(): bool
    {
        return $this->refresh;
    }

    /**
     * Set name
     */
    public function setName($name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Return name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set front-end label
     */
    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Return front-end label
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Return i18n localized front-end label
     */
    public function getI18nLabel(): string
    {
        return _t(__CLASS__ . '.HANDLER_LABEL', $this->getLabel());
    }

    /**
     * Returns the URL for this RequestHandler.
     *
     * @param string $action
     * @return string
     */
    public function Link($action = null)
    {
        return Controller::join_links($this->tabulatorGrid->Link(), 'bulkAction', $action);
    }

    /**
     * Returns the list of record IDs selected in the front-end.
     */
    public function getRecordIDList(): array
    {
        $vars = $this->tabulatorGrid->getRequest()->requestVars();
        return $vars['records'] ?? [];
    }

    /**
     * Returns a DataList of the records selected in the front-end.
     */
    public function getRecords(): ?DataList
    {
        $ids = $this->getRecordIDList();

        if ($ids) {
            $class = $this->tabulatorGrid->getModelClass();
            return DataList::create($class)->byIDs($ids);
        }
        return null;
    }

    public function process(HTTPRequest $request): string
    {
        throw new Exception("Not implemented");
    }

    /**
     * Wrap the process call for this action in a generic way
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $response = new HTTPResponse();

        try {
            $message = $this->process($request);

            $body = json_encode([
                'success' => true,
                'message' => $message,
                'reload' => $this->reload,
                'refresh' => $this->refresh,
            ]);
            $response->setBody($body);
            $response->addHeader('Content-Type', 'application/json');
        } catch (Exception $ex) {
            $response->setStatusCode(500);
            $response->setBody($ex->getMessage());
        }

        return $response;
    }
}
