Sortable - Yii2 component to maintain sort field for specified db table.


# Installation
To import the component to your project, put the following line to the require section of your composer.json file:
```
"serj/sortable": "~1.0.0",
```

# Config
Let's assume a table of the following structure:
### cartoons
```
id  | title               | category_id | sort
----+---------------------+-------------+------
100 | Winnie the pooh     | 15          | 3000
----+-----------------------------------+------
101 | Kolobok (The loaf)  | 15          | 1000
----+---------------------+-------------+------
102 | Hedgehog in the fog | 15          | 2000
----+---------------------+-------------+------
```
When you want to query the items in the sorted order, you must assume that items with lower sort values go first (**ASC**).

To initialize component via app config, with **minimal required settings** config
```php
'components' => [
    //...
    'sortableCartoons' => [
        'class' => 'serj\sortable\Sortable',
        'targetTable' => 'cartoons',
        'grpField' => 'category_id'
    ]
]
```
*grpField* can be omitted if you need to sort the items hrough out the entire table.

**Extended settings**
```php
'components' => [
    //...
    'sortableCartoons' => [
        'class' => 'serj\sortable\Sortable',
        'targetTable' => 'cartoons',
        'grpField' => 'category_id',
        'pkField' => 'id',
        'srtField' => 'sort',
        'sortGap' => 1000
    ]
]
```

**Or if you want to use it directly**
```php
$sortableCartoons = new \serj\sortable\Sortable([
    'targetTable' => 'cartoons',
    'grpField' => 'category_id',
    'pkField' => 'id',
    'srtField' => 'sort',
    'sortGap' => 1000,
]);
```
## Usage

To insert item after id:102
```php
$sortVal = \Yii::$app->sortableCartoons->getSortVal(102, 'after', 15);
```
To insert before id:102
```php
$sortVal = \Yii::$app->sortableCartoons->getSortVal(102, 'before', 15);
```
Then, if you use ActiveRecord, you may insert a new record
```php
(new Cartoon)->setAttributes([title => 'Some title', category_id => 15, sort => $sortVal])->save();
```
If you created a new category, say category_id:16 and there are no items yet
```php
$sortVal = \Yii::$app->sortableCartoons->getIniSortVal();
```
