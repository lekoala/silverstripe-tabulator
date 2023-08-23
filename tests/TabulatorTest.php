<?php

namespace LeKoala\Tabulator\Tests;

use LeKoala\Tabulator\TabulatorGrid;
use LeKoala\Tabulator\TabulatorGrid_ItemRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Control\Controller;
use SilverStripe\Security\Group;

/**
 * Tests for Cms Actions module
 */
class TabulatorTest extends SapphireTest
{
    /**
     * Defines the fixture file to use for this test class
     * @var string
     */
    protected static $fixture_file = 'TabulatorTest.yml';

    protected static $extra_dataobjects = [];

    public function setUp(): void
    {
        parent::setUp();
        $controller = Controller::curr();
        $controller->config()->set('url_segment', 'test_controller');
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testItSorts()
    {
        // delete groups without sort
        $noSortGroup = Group::get()->filter('Sort', 0);
        $noSortGroup->removeAll();

        $list = Group::get();
        $t = new TabulatorGrid('Groups', 'Groups', $list);
        $rec = Group::get()->first();
        $controller = Controller::curr();
        $tir = new TabulatorGrid_ItemRequest($t, $rec, $controller);

        // {
        //     "1": 1,
        //     "2": 2,
        //     "3": 3
        // }
        $CurrentOrder = [];
        foreach ($list as $record) {
            $CurrentOrder[$record->ID] = $record->Sort;
        }

        // The record being edited
        $Data = [
            'ID' => 1,
            'Sort' => 1,
        ];
        // The new sort order
        $Sort = 2;

        $newSort = $tir->executeSort($Data, $Sort);

        $list = Group::get();

        // {
        //     "1": 2,
        //     "2": 1,
        //     "3": 3
        // }
        $NewOrder = [];
        foreach ($list as $record) {
            $NewOrder[$record->ID] = $record->Sort;
        }

        $this->assertEquals($Sort, $newSort);
        $this->assertNotEquals($CurrentOrder, $NewOrder);
    }
}
