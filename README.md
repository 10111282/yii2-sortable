Sortable - Yii2 component to maintain sort field for specified db table.

# Installation
To import the component to your project, put the following line to the require section of your composer.json file:
```
"serj/sortable": "~1.0.0"
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

To initialize component via app config, with **minimal required settings**
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

To get sort value for an item to be inserted **after** id:102
```php
$sortVal = \Yii::$app->sortableCartoons->getSortVal(102, 'after', 15);
```
To get sort value for an item to be inserted **before** id:102
```php
$sortVal = \Yii::$app->sortableCartoons->getSortVal(102, 'before', 15);
```
Then, if you use ActiveRecord, you may insert a new record like this
```php
(new Cartoon)->setAttributes([title => 'Some title', category_id => 15, sort => $sortVal])->save();
```
To get sort value for an item to be inserted **before**  all items (in terms of specific category)
```php
// 15 is a category_id (grpField)
$sortVal = \Yii::$app->sortableCartoons->getSortValBeforeAll(15);
```
To get sort value for an item to be inserted **after**  all items (in terms of specific category)
```php
// 15 is a category_id (grpField)
$sortVal = \Yii::$app->sortableCartoons->getSortValAfterAll(15);
```
You may need to call the function without parameter (do not pass 15) if you maintain sorting through out an entire table, not only in a specific category range (so, you do not use *grpField*). Of course you can not use the component in both ways simultaneously on the same table. If you have such a scenario, you have to have two sort columns with the component instance configured for each one respectively.
This is applicable for all sort value getting functions mentioned above.


If you created a new category, say *category_id*:16 and there are no items yet
```php
$sortVal = \Yii::$app->sortableCartoons->getIniSortVal();
```

If your table have a column which represents a state of a record (e.g. deleted, archived) you can specify it in the config as *deletedField*
```php
'deletedField' => 'is_deleted'
```
Thus, all tuples that have this column (is_deleted) set to *true* wont be taken in account.

