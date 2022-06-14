<?php

namespace LeKoala\Tabulator\BulkActions;

use SilverStripe\Control\HTTPRequest;
use LeKoala\Tabulator\AbstractBulkAction;

/**
 * Bulk action handler for unpublishing records.
 */
class BulkUnpublishAction extends AbstractBulkAction
{
    protected string $name = 'unpublish';
    protected string $label = 'Unpublish';
    protected bool $xhr = true;

    public function getI18nLabel(): string
    {
        return _t(__CLASS__ . '.UNPUBLISH_SELECT_LABEL', $this->getLabel());
    }

    public function process(HTTPRequest $request): string
    {
        $records = $this->getRecords() ?? [];
        $i = 0;
        foreach ($records as $record) {
            $record->doUnpublish();
            $i++;
        }
        $result = _t(__CLASS__ . ".RECORDSUNPUBLISHED", "{count} records unpublished", ["count" => $i]);
        return $result;
    }
}
