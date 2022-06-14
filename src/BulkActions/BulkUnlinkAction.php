<?php

namespace LeKoala\Tabulator\BulkActions;

use SilverStripe\Control\HTTPRequest;
use LeKoala\Tabulator\AbstractBulkAction;

/**
 * Bulk action handler for unlinking records.
 */
class BulkUnlinkAction extends AbstractBulkAction
{
    protected string $name = 'unlink';
    protected string $label = 'Unlink';
    protected bool $xhr = true;

    public function getI18nLabel(): string
    {
        return _t(__CLASS__ . '.UNLINK_SELECT_LABEL', $this->getLabel());
    }

    public function process(HTTPRequest $request): string
    {
        $ids = $this->getRecordIDList();
        if (!empty($ids)) {
            $this->tabulatorGrid->getDataList()->removeMany($ids);
        }
        $result = _t(__CLASS__ . ".RECORDSUNLINKED", "{count} records unlinked", ["count" => count($ids)]);
        return $result;
    }
}
