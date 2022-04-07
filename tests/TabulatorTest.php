<?php

namespace LeKoala\Tabulator\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Control\Controller;

/**
 * Tests for Cms Actions module
 */
class TabulatorTest extends SapphireTest
{
    /**
     * Defines the fixture file to use for this test class
     * @var string
     */
    // protected static $fixture_file = 'TabulatorTest.yml';

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

    public function testItWorks()
    {
        return $this->assertTrue(true);
    }
}
