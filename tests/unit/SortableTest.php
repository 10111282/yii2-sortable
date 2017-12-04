<?php

use serj\sortable\Sortable;


class SortableTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @var serj\sortable\Sortable
     */
    protected $sortInSingleCat;

    /**
     * @var serj\sortable\Sortable
     */
     
    
    protected $sortThroughAllCat;

    protected function _before()
    {
        $config = [
            'id' => 'test case',
            'basePath' => dirname(dirname(__DIR__)),
            'components' => [
                'db' => [
                    'class' => 'yii\db\Connection',
                    'dsn' => "pgsql:host=localhost;dbname=sortable_test",
                    'username' => 'postgres',
                    'password' => '',
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
    
    

    protected function _after()
    {
    }

    /**
     * Getting initial sort value. Must be the same as configured.
     */
    public function testIniSrt()
    {
        $sortVal = $this->sortInSingleCat->getIniSortVal();
        $this->assertTrue($sortVal === 1000);
        
        $sortVal = $this->sortThroughAllCat->getIniSortVal();
        $this->assertTrue($sortVal === 1000);
    }

    /**
     * Derive the uppermost sort value in terms of one category
     *
     * @throws Exception
     */
    public function testAboveAll()
    {
        $categoryId = 15;
        $sortVal = $this->sortInSingleCat->getSortValBeforeAll($categoryId);

        $this->assertTrue($sortVal < 1000);
    }
    
    /**
     * Derive the uppermost sort value through out thr entire table
     *
     * @throws Exception
     */
    public function testAboveAllGeneral()
    {
        $sortVal = $this->sortThroughAllCat->getSortValBeforeAll();
        $this->assertTrue($sortVal < 1000);
    }
    
     /**
     * Derive the lowermost sort value in terms of one category
     *
     * @throws Exception
     */
    public function testBelowAboveAll()
    {
        $categoryId = 15;
        $sortVal = $this->sortInSingleCat->getSortValAfterAll($categoryId);
        $this->assertTrue($sortVal > 3000);
    }

    /**
     * Derive the lowermost sort value through out thr entire table
     *
     * @throws Exception
     */
    public function testBelowAllGeneral()
    {
        $sortVal = $this->sortThroughAllCat->getSortValAfterAll();
        $this->assertTrue($sortVal > 3000);
    }

     /**
     * Derive sort value in the 'after' position in terms of one category
     *
     * @throws Exception
     */
    public function testDeriveSortAfter()
    {
        $categoryId = 15;
        $recordId = 5;
        $sortVal = $this->sortInSingleCat->getSortVal($recordId, 'after', $categoryId);
        $this->assertTrue($sortVal > 1000 && $sortVal < 2000);
    }

    /**
     * Derive sort value in the 'after' position through out the entire table
     *
     * @throws Exception
     */
    public function testDeriveSortAfterGeneral()
    {
        $recordId = 5;
        $sortVal = $this->sortThroughAllCat->getSortVal($recordId, 'after');
        $this->assertTrue($sortVal > 2000 && $sortVal < 3000);
    }

    /**
     * Derive sort value in the 'before' position in terms of one category
     *
     * @throws Exception
     */
    public function testDeriveSortBefore()
    {
        $categoryId = 15;
        $recordId = 5;
        $sortVal = $this->sortInSingleCat->getSortVal($recordId, 'before', $categoryId);
        $this->assertTrue($sortVal < 1000);
    }

    /**
     * Derive sort value in the 'before' position through out the entire table
     *
     * @throws Exception
     */
    public function testDeriveSortBeforeGeneral()
    {
        $recordId = 5;
        $sortVal = $this->sortThroughAllCat->getSortVal($recordId, 'before');
        $this->assertTrue($sortVal < 2000 && $sortVal > 1000);
    }

    /**
     * Find out what item is located before or after
     * @throws Exception
     */
    public function testGetPrimaryKey()
    {
        $categoryId = 15;
        $recordId = 4;
        
        $id = $this->sortInSingleCat->getPk($recordId, 'before', $categoryId);
        $this->assertTrue($id === 6);
        
        // out of range
        $id = $this->sortInSingleCat->getPk($recordId, 'after', $categoryId);
        $this->assertTrue($id === false);
        
        $recordId = 5;
        $id = $this->sortInSingleCat->getPk($recordId, 'after', $categoryId);
        $this->assertTrue($id === 6);
    }

    /**
     * Test sort values rebuilding cosed by getting value after targeted one
     *
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function testSortRebuildOnAfter() {
        $categoryId = 15;
        $targetLocalId = 5;
        $targetGeneralId = 4;
        
        for ($i = 0; $i < 100; $i++) {
            $localSrt = $this->sortInSingleCat->getSortVal($targetLocalId, 'after', $categoryId);
            $generalSrt = $this->sortThroughAllCat->getSortVal($targetGeneralId, 'after');
            
            $query = "
              INSERT INTO cartoons (title, category_id, sort_local, sort_general)
              VALUES ('South Park - {$i}', {$categoryId}, {$localSrt}, {$generalSrt});
            ";

            (new \yii\db\Query())->createCommand()->setSql($query)->execute();
            
            if ($i === 0) {
                $firstId =  (new \yii\db\Query())->createCommand()
                    ->setSql("SELECT currval('cartoons_id_seq')")
                    ->queryScalar();
            }
        }
        
        $lastId =  (new \yii\db\Query())->createCommand()
            ->setSql("SELECT currval('cartoons_id_seq')")
            ->queryScalar();
            
        // sort in category  
        $this->assertTrue(6 === $this->sortInSingleCat->getPk($firstId, 'after', $categoryId));        
        $this->assertTrue($targetLocalId === $this->sortInSingleCat->getPk($lastId, 'before', $categoryId));  
        
        // general sort      
        $this->assertTrue(7 == $this->sortThroughAllCat->getPk($firstId, 'after'));        
        $this->assertTrue($targetGeneralId === $this->sortThroughAllCat->getPk($lastId, 'before'));  
    }
     
    /**
     * Test sort values rebuilding cosed by getting value before targeted one
     *
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function testSortRebuildOnBefore() {
        $categoryId = 15;
        $targetLocalId = 6;
        $targetGeneralId = 4;
        
        for ($i = 0; $i < 100; $i++) {
            $localSrt = $this->sortInSingleCat->getSortVal($targetLocalId, 'before', $categoryId);
            $generalSrt = $this->sortThroughAllCat->getSortVal($targetGeneralId, 'before');
            
            $query = "
              INSERT INTO cartoons (title, category_id, sort_local, sort_general)
              VALUES ('South Park - {$i}', {$categoryId}, {$localSrt}, {$generalSrt});
            ";

            (new \yii\db\Query())->createCommand()->setSql($query)->execute();
            
            if ($i === 0) {
                $firstId =  (new \yii\db\Query())->createCommand()
                    ->setSql("SELECT currval('cartoons_id_seq')")
                    ->queryScalar();
            }
        }
        
        $lastId =  (new \yii\db\Query())->createCommand()
            ->setSql("SELECT currval('cartoons_id_seq')")
            ->queryScalar();
            
        // sort in terms of one category
        $this->assertTrue(5 === $this->sortInSingleCat->getPk($firstId, 'before', $categoryId));        
        $this->assertTrue($targetLocalId === $this->sortInSingleCat->getPk($lastId, 'after', $categoryId));  
        
        // sort over the entire table
        $this->assertTrue(5 == $this->sortThroughAllCat->getPk($firstId, 'before'));        
        $this->assertTrue($targetGeneralId === $this->sortThroughAllCat->getPk($lastId, 'after'));  
    }
}