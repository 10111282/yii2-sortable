<?php

use serj\sortable\Sortable;


class SortablePostgresTest extends SortableBase
{
    protected function _before()
    {
        $config = [
            'id' => 'test case',
            'basePath' => dirname(dirname(__DIR__)),
            'components' => [
                'db' => [
                    'class' => 'yii\db\Connection',
                    'dsn' => "pgsql:host=sortable-postgres;dbname=sortable_test",
                    'username' => 'postgres',
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
                    'dbComponentId' => 'db'
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
                    'dbComponentId' => 'db'
                ]
            ]    
        ];
        
        new yii\console\Application($config);
        
        $this->sortInSingleCat = \Yii::$app->sortInSingleCat;
        $this->sortThroughAllCat = \Yii::$app->sortThroughAllCat;
    }
}