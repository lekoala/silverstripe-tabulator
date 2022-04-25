# SilverStripe Tabulator module

[![Build Status](https://app.travis-ci.com/lekoala/silverstripe-tabulator.svg?branch=master)](https://app.travis-ci.com/lekoala/silverstripe-tabulator)
[![scrutinizer](https://scrutinizer-ci.com/g/lekoala/silverstripe-tabulator/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/lekoala/silverstripe-tabulator/)
[![Code coverage](https://codecov.io/gh/lekoala/silverstripe-tabulator/branch/master/graph/badge.svg)](https://codecov.io/gh/lekoala/silverstripe-tabulator)

## Intro

Integrating [Tabulator](http://www.tabulator.info/) into SilverStripe. Because GridField doesn't always cut it.

## Configuring detail fields

When editing or viewing a record, the default `getCMSFields` is used. This might not always be ideal (fields may have gridfields or depend on some implementation).
To return a custom list, you can use `tabulatorCMSFields`. In order to help you get a decent set of fields, the `TabulatorScaffolder` class is here to help.

```php
public function tabulatorCMSFields(array $_params = [])
{
    $fields = TabulatorScaffolder::scaffoldFormFields($this, [
        // Don't allow has_many/many_many relationship editing before the record is first saved
        'includeRelations' => ($this->ID > 0),
        'tabbed' => true,
        'ajaxSafe' => true
    ]);
    $this->extend('updateCMSFields', $fields);
    return $fields;
}
```

You may or may not wish to trigger `updateCMSFields`. On the good side, it will help with extensions not aware of tabulator.
On the bad side, it can break because it most probably expect gridfields everywhere.

## Configuring columns

This module scaffold some default columns based on summary and searchable fields.

For more advanced usage, please define a `tabulatorColumns` method that returns all the columns according to Tabulator definitions.

## Configuring row actions

To define custom actions, you can either use the `addButton` method that will trigger an action on the current controller.

In order to forward actions to a record (the preferred way), add a `tabulatorRowActions` on your record.

```php
public function tabulatorRowActions()
{
    return [
        [
            'action' => 'doTabulatorAction',
            'title' => 'Do This',
            'icon' => 'favorite_border'
        ],
    ];
}
```

This will call the method `doTabulatorAction` on your record.

## Additionnal formaters

- SSTabulator.flagFormatter: format a two char country code to a svg flag using Last Icon

## Dependencies

- [Last Icon](https://github.com/lekoala/last-icon): for nice icons
- [Luxon](https://moment.github.io/luxon/#/): for formatting dates

## Compatibility

4.10+

## Maintainer

LeKoala - thomas@lekoala.be
