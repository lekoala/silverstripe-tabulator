<?php

namespace LeKoala\Tabulator\BulkActions;

use SilverStripe\Control\HTTPRequest;
use LeKoala\Tabulator\AbstractBulkAction;

/**
 * Bulk action handler for deleting records.
 */
class BulkDeleteAction extends AbstractBulkAction
{
    protected string $name = 'delete';
    protected string $label = 'Delete';
    protected bool $xhr = true;
    protected bool $destructive = true;

    public function getI18nLabel(): string
    {
        return _t(__CLASS__ . '.DELETE_SELECT_LABEL', $this->getLabel());
    }

    public function process(HTTPRequest $request): string
    {
        $records = $this->getRecords() ?? [];
        $i = 0;
        foreach ($records as $record) {
            $record->delete();
            $i++;
        }
        $result = _t(__CLASS__ . ".RECORDSDELETED", "{count} records deleted", ["count" => $i]);
        return $result;
    }
}
