<?php

namespace serj\sortable;

use yii\base\Component;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\Query;

/**
 * Sortable - Yii2 component to maintain sort column in relational database table.
 *
 * Let's assume table of the following structure:
 *
 * cartoons
 *
 * id |        title        | category_id | sort_inner | sort_general | archived | color 
 *----+---------------------+-------------+------------+--------------+----------+-------
 *  1 | Fiddlesticks        |          14 |       1000 |         7000 | t        | t
 *  2 | Trolley Troubles,   |          14 |       2000 |         8000 | f        | f
 *  3 | Fantasmagorie       |          14 |       3000 |         9000 | t        | f
 *  4 | Winnie the pooh     |          15 |       3000 |         3000 | f        | t
 *  5 | Kolobok (The loaf)  |          15 |       1000 |         2000 | f        | t
 *  6 | Hedgehog in the fog |          15 |       2000 |         1000 | f        | t
 *
 * Items must be sorted by sort ASC. So the items with lower sort values go first.
 *
 * To initialize component via app config
 *
 * 'sortInSingleCat' => [
 *     'class' => 'serj\sortable\Sortable',
 *     'targetTable' => 'cartoons',
 *     'pkColumn' => 'id',
 *     'srtColumn' => 'sort_inner',
 *     'sortGap' => 1000,
 * ]
 *
 * 'sortThroughAllCat' => [
 *     'class' => 'serj\sortable\Sortable',
 *     'targetTable' => 'cartoons',
 *     'grpColumn' => 'category_id',
 *     'pkColumn' => 'id',
 *     'srtColumn' => 'sort_general',
 *     'sortGap' => 1000,
 * ]
 *
 *
 *
 * To get sort value for an item to be inserted after id:5
 * $sortValLocal = \Yii::$app->sortInSingleCat->getSortVal(5, 'after', 15);
 * $sortValGeneral = \Yii::$app->sortThroughAllCat->getSortVal(5, 'after');
 *
 * Class Sortable
 * @package serj\sortable
 */
class Sortable extends Component
{
    const DB_DRIVER_PG = 'postgres';
    const DB_DRIVER_MYSQL = 'mysql';

    /**
     * @var string Database table name that holds sortable records.
     */
    public $targetTable;

    /**
     * It may be, for instance user_id or catalog_id etc.
     * If it not specified, then all items across the entire table will be treated as in one sort scope.
     *
     * @var string Column which groups items to sort through.
     */
    public $grpColumn;

    /**
     * Column name for primary key in the targetTable.
     *
     * @var string
     */
    public $pkColumn = 'id';

    /**
     * Column name representing a sort (or order in other words) field in the targetTable.
     *
     * @var string
     */
    public $srtColumn = 'sort';

    /**
     * It could be any integer grater than 1.
     * The grater the number the more rare batch update for sort field will happen.
     *
     * @var int Initial interval between sort values for nearest items.
     */
    public $sortGap = 1000;

    /**
     * Column names and its respective values to skip those records that have such values.
     * For example it could be ['deleted' => true]
     * So records with a status deleted set to true wont be taken in account.
     *
     * @var array
     */
    public $skipRows = [];

    /**
     * Component name responsible for database connection.
     * Should be one that's configured in application config file.
     * 
     * @var sting
     */
    public $dbComponentId = 'db';

    /**
     * Database, by default it's Postgres. You need to specify MySql for other databases,
     * since Postgres and MySql have a few syntax differences
     *
     * @var string
     */
    public $databaseDriver = self::DB_DRIVER_PG;

    /**
     * @var Connection
     */
    protected $db;


    /**
     * @inheritdoc
     */
    function init() 
    {
        parent::init();

        $this->db = \Yii::$app->{$this->dbComponentId};
    }

    /**
     * @param Connection $db
     */
    public function setDb(Connection $db) 
    {
        $this->db = $db;
    }

    /**
     * Returns initial sort value. Use it to get sort value when you insert record for the first time.
     * It can be first record in the entire table, or in particular scope if grpColumn in use.
     *
     * @return int
     */
    public function getIniSortVal()
    {
        return $this->sortGap;
    }

    /**
     * Derives next sort value for the record to be inserted after or before $targetId.
     * If it necessary, the sort value of records following the $targetId will be reset automatically.
     *
     * @param int $targetId Record id after or before which a new record supposed to be inserted.
     * @param string $position The possible options are: 'after', 'before'. Specifies how to interpret the $targetId.
     * @param null|int $groupingId Id of the grouping entity. If it not passed the $targetId will be used in a sub-query
     * to derived groupingId value. It has sense only if $this->grpColumn is not null.
     * @return int
     * @throws SortableException
     */
    public function getSortVal($targetId, $position = 'after', $groupingId = null)
    {
        if (!$position || !in_array($position, ['after', 'before'])) {
            throw new \Exception('You must specify a valid position: "after" or "before".');
        }

        if (false === ($sortVal = $this->deriveSortVal($targetId, $position, $groupingId))) {
            $this->rebuildSortAfter($targetId, $position != 'after', $groupingId);
            $sortVal = $this->deriveSortVal($targetId, $position, $groupingId);

            if (!$sortVal) throw new SortableException(
                'Sort value can not be derived. Check if all sort values in the same scope are unique.'
            );
        }

        return $sortVal;
    }

    /**
     * Derives a sort value for a record to be inserted before all items.
     *
     * @param null|int $groupingId
     * @return int
     * @throws SortableException
     */
    public function getSortValBeforeAll($groupingId = null)
    {
        if ($groupingId === null && $this->grpColumn) {
            throw new SortableException(
                'groupingId may be omitted only when grpColumn is not configured.'
            );
        }

        $query = (new Query())
            ->select([$this->pkColumn, $this->srtColumn])
            ->from($this->targetTable)
            ->where([
                'and',
                $this->grpColumn ? ['=', $this->grpColumn, $groupingId] : [],
                $this->skipRowsClause()
            ])
            ->orderBy([$this->srtColumn => SORT_ASC])
            ->limit(1);

        $result = $query->one($this->db);

        if ($result && $result[$this->srtColumn] == 1) {
            $this->rebuildSortAfter($result[$this->pkColumn], true);
            $sortVal = $this->getIniSortVal();
        }
        else if ($result) {
            $sortVal = ceil($result[$this->srtColumn] / 2);
        }
        else $sortVal = $this->getIniSortVal();

        return (int)$sortVal;
    }

    /**
     * Derives a sort value for a record to be inserted after all items.
     * 
     * @param null|int $groupingId
     * @return int
     * @throws SortableException
     */
    public function getSortValAfterAll($groupingId = null)
    {
        if (!$groupingId === null && $this->grpColumn) {
            throw new SortableException(
                'groupingId may be omitted only when grpColumn is not configured.'
            );
        }

        $query = (new Query())
            ->select($this->srtColumn)
            ->from($this->targetTable)
            ->where([
                'and',
                $this->grpColumn ? ['=', $this->grpColumn, $groupingId] : [],
                $this->skipRowsClause()
            ])
            ->orderBy($this->srtColumn.' DESC')
            ->limit(1);

        $result = $query->one($this->db);

        if ($result) {
            $result = array_values($result);
            $sortVal = $result[0] + $this->sortGap;
        }
        else $sortVal = $this->getIniSortVal();

        return (int)$sortVal;
    }

    /**
     * Derives sort value. If it not possible (there is an internal logic of the component) to get the value then false
     * will be returned. So no necessary data modifications happen for maintaining sort values.
     *
     * @param int $targetId Record id after or before which a new record supposed to be inserted.
     * @param string $position The possible options are: 'after', 'before'. Specifies how to interpret the $targetId.
     * @param null|int $groupingId Id of the grouping entity. If it not passed the $targetId will be used in a sub-query
     * to derived its value. It has sense only if grpColumn is configured.
     * @return bool|int Returns false if it's not possible to derive a sort value,
     * thus sort field for all items following $targetId (including $targetId itself if $position == 'before')
     * must be incremented by $this->sortGap value.
     * @throws SortableException
     */
    public function deriveSortVal($targetId, $position = 'after', $groupingId = null)
    {
        if (!$position || !in_array($position, ['after', 'before'])) {
            throw new SortableException('You must specify correct position: "after" or "before".');
        }

        $sort = false;

        if ($this->grpColumn) {
            if ($groupingId !== null) {
                $subQueryGroupId = $groupingId;
            }
            else {
                $subQueryGroupId = (new Query())
                    ->select($this->grpColumn)
                    ->from($this->targetTable)
                    ->where([$this->pkColumn => $targetId]);
            }
        }

        $subQuery = (new Query())
            ->select($this->srtColumn)
            ->from($this->targetTable)
            ->where([
                'and',
                $this->grpColumn ? ['=', $this->grpColumn, $subQueryGroupId] : [],
                [$this->pkColumn => $targetId]
            ]);

        $query = (new Query())
            ->select($this->srtColumn)
            ->from($this->targetTable)
            ->where([
                'and',
                $this->grpColumn ? ['=', $this->grpColumn, $subQueryGroupId] : [],
                $position == 'after' ? ['>=', $this->srtColumn, $subQuery] : ['<=', $this->srtColumn, $subQuery],
                $this->skipRowsClause()
            ])
            ->orderBy($position == 'after' ? $this->srtColumn.' ASC' : $this->srtColumn.' DESC')
            ->limit(2);

        $result = $query->all($this->db);
        $result = array_values($result);

        if (!count($result)) {
            $withGrpMsg = $this->grpColumn ? "with spcified {$this->grpColumn}" : '';
            throw new SortableException(
                sprintf('Record [ %d ] %s to calculate a sort value not found', $targetId, $withGrpMsg)
            );
        }

        if (count($result) == 2) {
            $sort = (int)ceil(($result[0][$this->srtColumn] + $result[1][$this->srtColumn]) / 2);
            if ($sort == $result[0][$this->srtColumn] || $sort == $result[1][$this->srtColumn]) $sort = false;
        }
        else if (count($result) == 1) {
            $sort = $position == 'after' ?
                (int)ceil($result[0][$this->srtColumn] + $this->sortGap) : (int)ceil($result[0][$this->srtColumn] / 2);
            if ($sort == $result[0][$this->srtColumn]) $sort = false;
        }

        return $sort;
    }

    /**
     * Returns an id of the item before or after $targetId, depending on the $position.
     * Returns false if $targetId does not exist or it's the first or last item in the list.
     *
     * @param int $targetId
     * @param string $position The possible options are: 'after', 'before'. Specifies how to interpret the $targetId.
     * @param null|int $groupingId Id of the grouping entity. If it not passed the $targetId will be used in a sub-query
     * to derived its value. It has sense only if $this->grpColumn is not null.
     * @return integer|bool
     * @throws SortableException
     */
    public function getPk($targetId, $position, $groupingId = null)
    {
        if (!$position || !in_array($position, ['after', 'before'])) {
            throw new SortableException('You must specify correct position: "after" or "before".');
        }

        if ($this->grpColumn) {
            if ($groupingId !== null) {
                $subQueryGroupId = $groupingId;
            }
            else {
                $subQueryGroupId = (new Query())
                    ->select($this->grpColumn)
                    ->from($this->targetTable)
                    ->where([$this->pkColumn => $targetId]);
            }
        }

        $subQuery = (new Query())
            ->select($this->srtColumn)
            ->from($this->targetTable)
            ->where([
                'and',
                $this->grpColumn ? ['=', $this->grpColumn, $subQueryGroupId] : [],
                [$this->pkColumn => $targetId]
            ]);

        $query = (new Query())
            ->select([$this->pkColumn])
            ->from($this->targetTable)
            ->where([
                'and',
                $this->grpColumn ? ['=', $this->grpColumn, $subQueryGroupId] : [],
                $position == 'after' ? ['>', $this->srtColumn, $subQuery] : ['<', $this->srtColumn, $subQuery]
            ])
            ->orderBy($position == 'after' ? $this->srtColumn.' ASC' : $this->srtColumn.' DESC')
            ->limit(1);

        $result = $query->scalar($this->db);

        return is_bool($result) ? $result : (int)$result;
    }

     /**
     * @param int $afterId
     * @param bool $includeMe True to update $afterId itself along with another rows.
     * @param null|int $groupingId Id of the grouping entity. If it not passed the $targetId will be used in a sub-query.
     * to derived its value. It has sense only if $this->grpColumn is not null.
     * @return int Number of rows affected by the execution.
     * @throws \yii\db\Exception
     */
    protected function rebuildSortAfter($afterId, $includeMe = false, $groupingId = null)
    {
        if ($this->databaseDriver === self::DB_DRIVER_PG) {
            $subQuerySortVal = (new Query())
                ->select($this->srtColumn)
                ->from($this->targetTable)
                ->where([$this->pkColumn => $afterId]);
        }
        else {
            $subQuerySortVal = (new Query())->select($this->srtColumn.' as sort_column')
                ->from($this->targetTable)
                ->where([$this->pkColumn => $afterId])
                ->one()['sort_column'];
        }

        if ($this->grpColumn) {
            if ($groupingId) {
                $subQueryGroupId = $groupingId;
            }
            else {
                if ($this->databaseDriver === self::DB_DRIVER_PG) {
                    $subQueryGroupId = (new Query())
                        ->select($this->grpColumn)
                        ->from($this->targetTable)
                        ->where([$this->pkColumn => $afterId]);
                }
                else {
                    $subQueryGroupId = (new Query())
                        ->select($this->grpColumn.' as group_column')
                        ->from($this->targetTable)
                        ->where([$this->pkColumn => $afterId])
                        ->one()['group_column'];
                }
            }
        }

        return $this->db->createCommand()
            ->update(
                $this->targetTable,
                [$this->srtColumn => new Expression("{$this->srtColumn} + {$this->sortGap}")],
                [
                    'and',
                    [$includeMe ? '>=' : '>', $this->srtColumn, $subQuerySortVal],
                    $this->grpColumn ? [$this->grpColumn => $subQueryGroupId] : []
                ]
            )
            ->execute();
    }

    /**
     * Returns a clause to be used in queries to omit rows which supposed to be skipped in accordance to config.
     *
     * @return array
     */
    protected function skipRowsClause() {
        $skipClause = [];
        foreach ($this->skipRows as $cl => $val) {
            $skipClause[] = ['<>', $cl, $val];
        }
        if (count($skipClause) > 1) array_unshift($skipClause, 'and');

       return $skipClause;
    }
}