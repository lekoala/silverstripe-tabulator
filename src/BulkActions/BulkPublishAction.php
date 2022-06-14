<?php

namespace LeKoala\Tabulator\BulkActions;

use SilverStripe\Control\HTTPRequest;
use LeKoala\Tabulator\AbstractBulkAction;

/**
 * Bulk action handler for publishing records.
 */
class BulkPublishAction extends AbstractBulkAction
{
    protected string $name = 'publish';
    protected string $label = 'Publish';
    protected bool $xhr = true;

    public function getI18nLabel(): string
    {
        return _t(__CLASS__ . '.PUBLISH_SELECT_LABEL', $this->getLabel());
    }

    public function process(HTTPRequest $request): string
    {
        $i = 0;
        foreach ($this->getRecords() as $record) {
            $record->publishRecursive();
            $i++;
        }
        $result = _t(__CLASS__ . ".RECORDSPUBLISHED", "{count} records published", ["count" => $i]);
        return $result;
    }
}
