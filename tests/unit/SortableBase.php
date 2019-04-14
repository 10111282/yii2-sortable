<?php

use serj\sortable\Sortable;


class SortableBase extends \Codeception\Test\Unit
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

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var string
     */
    protected $dbDriver = Sortable::DB_DRIVER_PG;

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

        $categoryId = 0;
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
    public function testBelowAll()
    {
        $categoryId = 15;
        $sortVal = $this->sortInSingleCat->getSortValAfterAll($categoryId);
        $this->assertTrue($sortVal > 3000);

        $categoryId = 0;
        $sortVal = $this->sortInSingleCat->getSortValAfterAll($categoryId);
        $this->assertTrue($sortVal === 2000);
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

        $sortVal = $this->sortInSingleCat->getSortVal($recordId, 'after');
        $this->assertTrue($sortVal > 1000 && $sortVal < 2000);

        $categoryId = 0;
        $recordId = 10;
        $sortVal = $this->sortInSingleCat->getSortVal($recordId, 'after', $categoryId);
        $this->assertTrue($sortVal === 2000);
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

        $sortVal = $this->sortInSingleCat->getSortVal($recordId, 'before');
        $this->assertTrue($sortVal < 1000);

        $categoryId = 0;
        $recordId = 10;
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
            $localSrt = $this->sortInSingleCat->getSortVal($targetLocalId, 'after', null);
            $generalSrt = $this->sortThroughAllCat->getSortVal($targetGeneralId, 'after');

            $query = "
              INSERT INTO cartoons (title, category_id, sort_local, sort_general, archived, color)
              VALUES ('South Park - {$i}', {$categoryId}, {$localSrt}, {$generalSrt}, false, true);
            ";

            $r = (new \yii\db\Query())->createCommand()->setSql($query)->execute();

            if ($i === 0) {
                $firstId = $this->getAutoincrementVal();
            }
        }

        $lastId = $this->getAutoincrementVal();

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
              INSERT INTO cartoons (title, category_id, sort_local, sort_general, archived, color)
              VALUES ('South Park - {$i}', {$categoryId}, {$localSrt}, {$generalSrt}, false, true);
            ";

            (new \yii\db\Query())->createCommand()->setSql($query)->execute();
            
            if ($i === 0) {
                $firstId = $this->getAutoincrementVal();
            }
        }
        
        $lastId = $this->getAutoincrementVal();
            
        // sort in terms of one category
        $this->assertTrue(5 === $this->sortInSingleCat->getPk($firstId, 'before', $categoryId));        
        $this->assertTrue($targetLocalId === $this->sortInSingleCat->getPk($lastId, 'after', $categoryId));  
        
        // sort over the entire table
        $this->assertTrue(5 == $this->sortThroughAllCat->getPk($firstId, 'before'));        
        $this->assertTrue($targetGeneralId === $this->sortThroughAllCat->getPk($lastId, 'after'));  
    }

    protected function getAutoincrementVal() {
        if ($this->dbDriver === Sortable::DB_DRIVER_PG) {
            $id = (new \yii\db\Query())->createCommand()
                ->setSql("SELECT currval('cartoons_id_seq')")
                ->queryScalar();
        } else {
             $id = (int)(new \yii\db\Query())->createCommand()
                ->setSql("
                    SELECT `AUTO_INCREMENT`
                    FROM  INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_NAME   = 'cartoons'
                ")
                ->queryScalar();
             $id -= 1; //mysql returns next autoincrement value, not the last one
        }

        return $id;
    }
}