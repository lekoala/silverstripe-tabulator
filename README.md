# SilverStripe Tabulator module

[![Build Status](https://app.travis-ci.com/lekoala/silverstripe-tabulator.svg?branch=master)](https://app.travis-ci.com/lekoala/silverstripe-tabulator)
[![scrutinizer](https://scrutinizer-ci.com/g/lekoala/silverstripe-tabulator/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/lekoala/silverstripe-tabulator/)
[![Code coverage](https://codecov.io/gh/lekoala/silverstripe-tabulator/branch/master/graph/badge.svg)](https://codecov.io/gh/lekoala/silverstripe-tabulator)

## Intro

Integrating [Tabulator](http://www.tabulator.info/) into SilverStripe. Because GridField doesn't always cut it.
This works in the front end and in the cms.

This module is used in [my alternative admin module](https://github.com/lekoala/silverstripe-admini) and in a production project using fairly complex front end editable tables.
This means that I will have probably faced most of the issues you might have in your own projects :-)

It supports many features out of the boxes:

-   Inline editing
-   Bulk actions
-   Custom action on the table or in the edit form
-   Moveable rows by drag and drop
-   Filtering, sorting, pagination...

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

For more advanced usage, please define a `tabulatorColumns` method that returns all the columns according to [Tabulator definitions](http://tabulator.info/docs/5.5/columns).

## JS Init

By default, Tabulator will init through a custom elements provided by [formidable elements](https://formidable-elements.vercel.app/)

## Options

You can configure any of the [available options](http://tabulator.info/docs/5.5/options) using the `setOption` call.

### Dynamic callbacks

For dynamic callbacks, you can specify a function available in the global namespace using a namespaced function to avoid scope pollution.
These callbacks are the same as the one of [formidable elements](https://formidable-elements.vercel.app/) meaning they have a form of {"\_\_fn" : "app.callback"}

Some defaults callback are available under SSTabulator.

For example:

```php
$grid->registerJsNamespace('MyApp');
$grid->addColumn('MyCell', 'My Cell', [
    'headerSort' => false,
    'editable' => '_editable',
    'editor' => ["__fn" => 'SSTabulator.externalEditor'],
    'mutatorEdit' => ["__fn" => 'MyApp.mutateValue'],
]);
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

Upon blur, it will trigger a ajaxEdit request on the editUrl endpoint if set.

## Buttons

### Row buttons

You can create button columns with the `makeButton` function. Under the hood, it will use the buttonFormatter and
buttonHandler.

You can also enable ajax mode by setting the `ajax` parameter either as true/1 or as a string.
Using true/1 will use built-in ajax handler, or you can choose to pick any global function name

```php
$btn = $grid->makeButton("myaction/{ID}", "", "Confirm");
$btn['width'] = '100';
$btn['tooltip'] = '';
$btn['formatterParams']['classes'] = "btn btn-primary d-block";
$btn['formatterParams']['ajax'] = "*MyApp.handleAjax";
$grid->addButtonFromArray("MyBtn", $btn);
```

You custom handler just look like this and return a promise

```js
var handleAjax = function (e, cell, btn, formData) {
    // do something here
    // return promise
};
```

Buttons are not responsive by default. Simply unset the responsive key if needed

```php
unset($btn['responsive']);
```

### Row actions in the CMS

In order to forward actions to a record (the preferred way), add a `tabulatorRowActions` on your record.

```php
public function tabulatorRowActions()
{
    return [
        'doTabulatorAction' => [
            'title' => 'Do This',
            'icon' => 'favorite_border',
            'ajax' => true, // submitted through xhr
            'reload' => true, // reload table data after action
            'refresh' => true, // refresh the whole page after action
        ],
    ];
}
```

This will call the method `doTabulatorAction` on your record.

## Tools

Tabulator supports "tools" that can be added above the grid. This is how, for example, the add new button is used.
All tools inherit from the `AbstractTabulatorTool` class.

Tools can handle action by calling /tools/$ID on the TabulatorGrid field.

### Generic tools

Because creating a class for every tool is tedious, you can do the following

```php
$grid->addToolEnd(new GenericTabulatorTool('my_unique_action', 'Do something unique', function () {
    return $this->doSomethingUnique();
}));
```

## Bulk actions

You can make items selectable and pass an array of actions. Actions must extend the `AbstractBulkAction` class.

```php
$grid->wizardSelectable([
    new BulkDeleteAction()
]);
```

See /src/BulkActions for a full list of actions. These are roughly the equivalent of those you can find in the BulkManager module.

### Generic bulk action

Because creating a class for every simple action is tedious, you can do the following

```php
$grid->wizardSelectable([
    new GenericBulkAction('bulk_cancel', 'Cancel', function (MyRecord $record, $grid) {
        return $record->doCancel();
    }),
    new GenericBulkAction('bulk_approve', 'Approve', function (MyRecord $record, $grid) {
        return $record->doApprove();
    }),
]);
```

## Listen to events

You can add custom listeners that should exist in the global namespace.

```php
$grid->addListener('tableBuilt', 'MyApp.onTableBuilt');
```

## Data attributes vs options

Some of the settings of Tabulator are set using data attributes. This has been made in order to avoid mixing custom behaviour with built-in options
from Tabulator. Function names should be regular strings, since they are json encoded and resolved by our custom code.

## Notifications

There is a built-in `notify` helper function that supports quite a few notification types by default.

If needed, you can register a global SSTabulator.notify function that will be called instead of the default function.

## Has one field

If you are using [silverstripe-hasonefield](https://github.com/silvershop/silverstripe-hasonefield/), I have a good news for you
because this module includes a basic (for now) support for a simple has one editing button.
Simply replace your instances of `HasOneButtonField` with `HasOneTabulatorField` and you should be good to go!

## Additionnal formaters and helpers

-   SSTabulator.boolGroupHeader: useful to group by boolean values
-   SSTabulator.isCellEditable: convention based callback to check if the row is editable

## Global search

Searching by columns is not always possible or convenient. Maybe you want to have a global search feature.

This is really easy, just do this. It will create a top search bar where you can type anything!

```php
   $grid->setGlobalSearch(true);
```

By default, it will make a PartialMatch against the string. For large tables, you might not want to do that to use your indexes properly.
You can also use shortcut syntax for filters:

-   s:
-   e:
-   =:
-   %:

You can set the search fields using `setWildcardFields`. Otherwise it will default to `searchableFields`.

### Quick filters

When global search is enabled, you can also provide a custom list of "quick filters".

```php
$grid->setQuickFilters([
        'blacklist' => [
            'label' => 'Blacklisted Companies',
            'callback' => function (&$list) {
                $blacklistedIDS = DB::query('SELECT ID FROM Company WHERE `Status` = \'Blacklisted\'')->column();
                $list = $list->filter('ID', $blacklistedIDS);
            }
        ]
    ]);
```

## Model options

If you add a `tabulatorOptions` method, you can configure how the model will be autoconfigured by Tabulator.

It can provide the followings keys:

-   summaryFields: use summaryFields() by default if not provided
-   searchableFields: use searchableFields by default if not provided
-   sortable: is sortable? (true by default if Sort field is present)
-   rowDelete: delete at row level (false by default)
-   addNew: show add new if allowed (true by default)
-   export: show export if configured (true by default)

For custom columns, please use `tabulatorColumns`.
For custom actions, please use `tabulatorRowActions`.

## Migrating from GridFields

If you are in a project currently using GridFields, there are a couple of ways you can slowly migrate to Tabulator.
One way is to inject Tabulator instead of a GridField so that all GridField::create call return a TabulatorGrid.
As a convenience, Tabulator define a getConfig method that returns a blank GridFieldConfig so that code expecting that doesn't crash.

Another way is to use the TabulatorGrid::replaceGridfield method that tries its best to replace your GridField instance with
an appropriate and configured TabulatorGrid.

## Optional Dependencies

-   [Last Icon](https://github.com/lekoala/last-icon): for nice icons
-   [Luxon](https://moment.github.io/luxon/#/): for formatting dates

## Compatibility

4.10+

## Maintainer

LeKoala - thomas@lekoala.be
