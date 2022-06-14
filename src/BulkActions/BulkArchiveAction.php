<?php

namespace LeKoala\Tabulator\BulkActions;

use SilverStripe\Control\HTTPRequest;
use LeKoala\Tabulator\AbstractBulkAction;

/**
 * Bulk action handler for archiving records.
 */
class BulkArchiveAction extends AbstractBulkAction
{
    protected string $name = 'archive';
    protected string $label = 'Archive';
    protected bool $xhr = true;

    public function getI18nLabel(): string
    {
        return _t(__CLASS__ . '.ARCHIVE_SELECT_LABEL', $this->getLabel());
    }

    public function process(HTTPRequest $request): string
    {
        $records = $this->getRecords() ?? [];
        $i = 0;
        foreach ($records as $record) {
            $record->doArchive();
            $i++;
        }
        $result = _t(__CLASS__ . ".RECORDSARCHIVED", "{count} records archived", ["count" => $i]);
        return $result;
    }
}
