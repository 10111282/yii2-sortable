# Sortable - Yii2 component to maintain sort column in relational database table.

## Installation
To import the component to your project, put the following line to the require section of your composer.json file:
```js
"serj/sortable": "~1.2.0"
```
or run the command
```bash
$ composer require serj/sortable "~1.2.0"
```


## Config
Let's assume a table of the following structure:
### cartoons
```
 id |        title        | category_id | sort_local | sort_general | archived | color
----+---------------------+-------------+------------+--------------+----------+-------
  1 | Fiddlesticks        |          14 |       1000 |         7000 | t        | t
  2 | Trolley Troubles,   |          14 |       2000 |         8000 | f        | f
  3 | Fantasmagorie       |          14 |       3000 |         9000 | t        | f
  4 | Winnie the pooh     |          15 |       3000 |         3000 | f        | t
  5 | Kolobok (The loaf)  |          15 |       1000 |         2000 | f        | t
  6 | Hedgehog in the fog |          15 |       2000 |         1000 | f        | t
  7 | South Park          |          16 |       1000 |         4000 | f        | t
  8 | Futurama            |          16 |       2000 |         5000 | f        | t
  9 | Rick and Morty      |          16 |       3000 |         6000 | f        | t

```
When you want to query items in the sorted order, you must assume that items with lower sort values go first (**ASC**).

To initialize component via app config, with **minimal required settings**
```php
'components' => [
    //...
    'sortableCartoons' => [
        'class' => 'serj\sortable\Sortable',
        'targetTable' => 'cartoons',
        'srtColumn' => 'sort_inner'
    ]
]
```
Let's look at more interesting scenario. Our table has 2 columns to maintain items order. *sort_inner* - for sorting in bounds of a category, *sort_general* - for sorting through out the entire table.

To maintain both columns we need two instances of the component, each one for its respective column.

```php
'components' => [
    'sortInSingleCat' => [
        'class' => 'serj\sortable\Sortable',
        'targetTable' => 'cartoons',
        'grpColumn' => 'category_id',
        'pkColumn' => 'id',
        'srtColumn' => 'sort_inner',
        'skipRows' => [
            'archived' => true,
            'color' => false
        ]
    ],
    'sortThroughAllCat' => [
        'class' => 'serj\sortable\Sortable',
        'targetTable' => 'cartoons',
        'pkColumn' => 'id',
        'srtColumn' => 'sort_general',
        'skipRows' => [
            'archived' => true,
            'color' => false
        ]
    ]
]
```

Or if you want to use it directly without config
```php
$sortThroughAllCat = new \serj\sortable\Sortable([
    'targetTable' => 'cartoons',
    'pkColumn' => 'id',
    'srtColumn' => 'sort_general',
    'skipRows' => [
        'archived' => true,
        'color' => false
    ]
]);
```
## Usage

To get sort value for an item to be inserted **after** id:5
```php
$sortValLocal = \Yii::$app->sortInSingleCat->getSortVal(5, 'after', 15);
$sortValGeneral = \Yii::$app->sortThroughAllCat->getSortVal(5, 'after');
```
To get sort value for an item to be inserted **before** id:5
```php
$sortValLocal = \Yii::$app->sortInSingleCat->getSortVal(5, 'before', 15);
$sortValGeneral = \Yii::$app->sortThroughAllCat->getSortVal(5, 'before');
```
Then, if you use ActiveRecord, you may insert a new record like this
```php
(new Cartoon)->setAttributes([
    'title' => 'Some title',
    'category_id' => 15,
    'sort_local' => $sortValLocal,
    'sort_general' => $sortValGeneral
])->save();
```
To get sort value for an item to be inserted **before**  all items
```php
// 15 is a category_id (srtColumn)
$sortValLocal = \Yii::$app->sortInSingleCat->getSortValBeforeAll(15);
$sortValGeneral = \Yii::$app->sortThroughAllCat->getSortValBeforeAll();
```
To get sort value for an item to be inserted **after**  all items (in terms of specific category)
```php
// 15 is a category_id (srtColumn)
$sortValLocal = \Yii::$app->sortableCartoons->getSortValAfterAll(15);
$sortValGeneral = \Yii::$app->sortThroughAllCat->getSortValAfterAll();
```

If you created a new category, say *category_id*:17 and there are no items yet
```php
$sortValLocal = \Yii::$app->sortableCartoons->getIniSortVal();

//to insert to the end of the list in terms of the entire table
$sortValGeneral = \Yii::$app->sortThroughAllCat->getSortValAfterAll();
```

If you table have a column or columns representing a state of a record (e.g. deleted, archived) which means you no longer use those records, or you just what to ignore them, you can specify it in the  config as *skipRows*. In this particular case those are *archived*, *color*.
```php
    'skipRows' => [
        'archived' => true,
        'color' => false
    ]
```
Thus, all tuples that have *archived* = *true* and *color* = *false* wont be taken in account. There is a gotcha: these states must be persistent, so once set they must not be reverted back. If you switch it back and forth, then do not use this option.

## Alternative database connection
By default the component uses *\Yii::$app->db*, if you have to use another connection
```php
$connection = new \yii\db\Connection($config)
\Yii::$app->sortableCartoons->setDb($connection);
```
Or set it up in the component config
```php
'components' => [
    'anotherDb' => [
        'class' => 'yii\db\Connection',
        ...
    ],
    ...
    'sortInSingleCat' => [
        'class' => 'serj\sortable\Sortable',
        'dbComponentId' => 'anotherDb'
        ...
    ],
    ...
]
```
