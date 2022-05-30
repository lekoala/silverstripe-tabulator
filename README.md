# SilverStripe Tabulator module

[![Build Status](https://app.travis-ci.com/lekoala/silverstripe-tabulator.svg?branch=master)](https://app.travis-ci.com/lekoala/silverstripe-tabulator)
[![scrutinizer](https://scrutinizer-ci.com/g/lekoala/silverstripe-tabulator/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/lekoala/silverstripe-tabulator/)
[![Code coverage](https://codecov.io/gh/lekoala/silverstripe-tabulator/branch/master/graph/badge.svg)](https://codecov.io/gh/lekoala/silverstripe-tabulator)

## Intro

Integrating [Tabulator](http://www.tabulator.info/) into SilverStripe. Because GridField doesn't always cut it.
This works in the front end and in the cms.

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
You can also use `makeButton` and `addButtonFromArray` for finer control.

### Row actions in the CMS

In order to forward actions to a record (the preferred way), add a `tabulatorRowActions` on your record.

```php
public function tabulatorRowActions()
{
    return [
        'doTabulatorAction' => [
            'title' => 'Do This',
            'icon' => 'favorite_border'
        ],
    ];
}
```

This will call the method `doTabulatorAction` on your record.

## JS requirements

To use in the SilverStripe admin, you need to enable lazy load behaviour. See `admin.js` file for this. It is NOT
automatically included, it's up to you to enable this if you plan to use Tabulator in the admin.
Also see: https://github.com/silverstripe/silverstripe-admin/issues/1308

## JS Init

Tabulator supports two mode of init. One using js include (the default mode) which works will in the SilverStripe
admin during ajax navigation, as the init script with its options is served as a distinct script.

This might not always be convenient in the frontend where ajax might not be used or x-include-js handled. In this
case, you can disable the configProvider and simply use a regular inline script.

```php
$grid->setControllerFunction(__FUNCTION__);
$grid->setUseConfigProvider(false);
```

## Using wizards

The class contain a couple of "wizard" functions that will set a group of options in a consistent manner.
Please check the source code for more informations.

## Theming

The class use by default the Bootstrap 5 theme with a few custom improvements to make it more "silverstripy".

You can choose your theme with the `theme` config option or set it to null to include your own theme.
Disabling the bootstrap5 theme also disables the custom css.

## Editing

You can make any column editable. Simply call `makeColumnEditable` and pass along relevant editor details.

Upon blur, it will trigger a ajaxEdit request.

## Notifications

There is a built-in `notify` helper function that supports quite a few notification types by default.

If needed, you can register a global SSTabulator.notify function that will be called instead of the default function.

## Additionnal formaters and helpers

- SSTabulator.flagFormatter: format a two char country code to a svg flag using Last Icon
- SSTabulator.buttonFormatter: format buttons
  - Allow showing alternative icons using `showAlt` and `showAltClause`
- SSTabulator.externalFormatter: format with an external function
- SSTabulator.customTickCross: nice alternative to the default tick cross formatter
- SSTabulator.boolGroupHeader: useful to group by boolean values
- SSTabulator.simpleRowFormatter: apply class or colors if the row contains `TabulatorRowColor` or `TabulatorRowClass`
- SSTabulator.expandTooltip: show content in a tooltip if truncated
- SSTabulator.moneyEditor: edit currencies
- SSTabulator.externalEditor: edit with an external script
- SSTabulator.isCellEditable: convention based callback to check if the row is editable

## Dependencies

- [Last Icon](https://github.com/lekoala/last-icon): for nice icons
- [Luxon](https://moment.github.io/luxon/#/): for formatting dates

## Compatibility

4.10+

## Maintainer

LeKoala - thomas@lekoala.be
