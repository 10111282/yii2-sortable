<?php

use serj\sortable\Sortable;


class SortableMySqlTest extends SortableBase
{
    protected function _before()
    {
        $this->config = [
            'id' => 'test case',
            'basePath' => dirname(dirname(__DIR__)),
            'components' => [
                'db' => [
                    'class' => 'yii\db\Connection',
                    'dsn' => "mysql:host=sortable-mysql;dbname=sortable_test",
                    'username' => 'tester',
                    'password' => 'secret',
                    'charset' => 'utf8',
                ],
                'sortInSingleCat' => [
                    'class' => 'serj\sortable\Sortable',
                    'targetTable' => 'cartoons',
                    'grpColumn' => 'category_id',
                    'pkColumn' => 'id',
                    'srtColumn' => 'sort_local',
                    'skipRows' => [
                        'archived' => true,
                        'color' => false
                    ],
                    'dbComponentId' => 'db',
                    'databaseDriver' => Sortable::DB_DRIVER_MYSQL
                ],
                'sortThroughAllCat' => [
                    'class' => 'serj\sortable\Sortable',
                    'targetTable' => 'cartoons',
                    'pkColumn' => 'id',
                    'srtColumn' => 'sort_general',
                    'skipRows' => [
                        'archived' => true,
                        'color' => false
                    ],
                    'dbComponentId' => 'db',
                    'databaseDriver' => Sortable::DB_DRIVER_MYSQL
                ]
            ]    
        ];

        $this->dbDriver = Sortable::DB_DRIVER_MYSQL;

        new yii\console\Application($this->config);
        
        $this->sortInSingleCat = \Yii::$app->sortInSingleCat;
        $this->sortThroughAllCat = \Yii::$app->sortThroughAllCat;
    }
}