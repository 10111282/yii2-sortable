<?php
namespace serj\sortable;

use yii\base\Component;
use yii\db\Expression;
use yii\db\Query;

/**
 * Component aimed to maintain sort field for specified db table.
 *
 * Let's assume table of the following structure:
 *
 * cartoons
 *
 * id  | title               | category_id | sort
 * ----+---------------------+-------------+------
 * 100 | Winnie the pooh     | 15          | 3000
 * ----+-----------------------------------+------
 * 101 | Kolobok (The loaf)  | 15          | 1000
 * ----+---------------------+-------------+------
 * 102 | Hedgehog in the fog | 15          | 2000
 * ----+---------------------+-------------+------
 *
 * Items must be sorted by sort ASC. So the items with lower sort values go first.
 *
 * To initialize component via app config
 *
 * 'sortableCartoons' => [
 *     'class' => 'serj\sortable\Sortable',
 *     'targetTable' => 'cartoons',
 *     'grpField' => 'category_id',
 *     'pkField' => 'id',
 *     'srtField' => 'sort',
 *     'sortGap' => 1000,
 * ]
 *
 * or if you want to use it directly
 *
 * $sortableCartoons = new \serj\sortable\Sortable([
 *     'targetTable' => 'cartoons',
 *     'grpField' => 'category_id',
 *     'pkField' => 'id',
 *     'srtField' => 'sort',
 *     'sortGap' => 1000,
 * ]);
 *
 * To insert an item after id:102
 * $sortVal = \Yii::$app->sortableCartoons->getSortVal(102, 'after', 15);
 *
 * To insert it before id:102
 * $sortVal = \Yii::$app->sortableCartoons->getSortVal(102, 'before', 15);
 *
 * Then, if you use ActiveRecord, you may insert a new record
 * (new Cartoons)->setAttributes([title => 'Some title', category_id => 15, sort => $sortVal])->save();
 *
 * If you add a new item  (say, under category_id:16) and there are no records in the table with such $grpField yet
 * $sortVal = \Yii::$app->sortableCartoons->getIniSortVal();
 *
 *
 * Class Sortable
 * @package serj\sortable
 */
class Sortable extends Component
{
    /**
     * @var string Database table name that holds sortable records.
     */
    public $targetTable;

    /**
     * @var string Field name for grouping field that creates a scope for items.
     * It may be user_id or catalog_id etc.
     * If it not specified, then all items across the entire table will be treated as in one scope.
     */
    public $grpField;

    /**
     * @var string Field name for primary key in the targetTable.
     */
    public $pkField = 'id';

    /**
     * @var string Field name that represents sort field in the targetTable.
     */
    public $srtField = 'sort';

    /**
     * @var int Initial interval between sort values for nearest items.
     * It could be any integer grater than 1. The grater number the more rare the sort field will be reset.
     */
    public $sortGap = 1000;

    /**
     * Field name for deleted flag.
     * @var bool|string
     */
    public $deletedField = false;


    /**
     * Returns initial sort value. Use it to get sort value when you insert record for the first time in the
     * particular scope (defined by $this->grpField)
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
     * to derived groupingId value. It has sense only if $this->grpField is not null.
     * @return int
     * @throws \Exception
     */
    public function getSortVal($targetId, $position = 'after', $groupingId = null)
    {
        if (!$position || !in_array($position, ['after', 'before'])) {
            throw new \Exception('You must specify a valid position: "after" or "before".');
        }

        if (!$sortVal = $this->deriveSortVal($targetId, $position, $groupingId)) {
            $this->rebuildSortAfter($targetId, $position != 'after');
            $sortVal = $this->deriveSortVal($targetId, $position);

            if (!$sortVal) throw new \Exception(
                'Sort value can not be derived. Check if all sort values in the same scope are unique.'
            );
        }

        return $sortVal;
    }

    /**
     * Derives a sort value for a record to be inserted before all items.
     *
     * @param null|int $groupingId
     * @return float|int
     * @throws \Exception
     */
    public function getSortValBeforeAll($groupingId = null)
    {
        if (!$groupingId && $this->grpField) {
            throw new \Exception(
                'groupingId may be omitted only when grpField is not configured.'
            );
        }

        $query = (new Query())
            ->select([$this->pkField, $this->srtField])
            ->from($this->targetTable)
            ->where([
                'and',
                $this->grpField ? ['=', $this->grpField, $groupingId] : [],
                $this->deletedField ? ['=', $this->deletedField, false] : []
            ]);

        $query->orderBy($this->srtField);
        $query->limit(1);

        $result = $query->one();

        if ($result && $result[$this->srtField] == 1) {
            $this->rebuildSortAfter($result[$this->pkField], true);
            $sortVal = $this->getIniSortVal();
        }
        else if ($result) {
            $sortVal = ceil($result[$this->srtField] / 2);
        }
        else $sortVal = $this->getIniSortVal();

        return $sortVal;
    }

    /**
     * Derives a sort value for a record to be inserted after all items.
     * 
     * @param null|int $groupingId
     * @return float|int
     * @throws \Exception
     */
    public function getSortValAfterAll($groupingId = null)
    {
        if (!$groupingId && $this->grpField) {
            throw new \Exception(
                'groupingId may be omitted only when grpField is not configured.'
            );
        }

        $query = (new Query())
            ->select($this->srtField)
            ->from($this->targetTable)
            ->where([
                'and',
                $this->grpField ? ['=', $this->grpField, $groupingId] : [],
                $this->deletedField ? ['=', $this->deletedField, false] : []
            ]);

        $query->orderBy($this->srtField.' DESC');
        $query->limit(1);

        $result = $query->one();

        if ($result) {
            $result = array_values($result);
            $sortVal = $result[0] + $this->sortGap;
        }
        else $sortVal = $this->getIniSortVal();

        return $sortVal;
    }

    /**
     * @param int $targetId Record id after or before which a new record supposed to be inserted.
     * @param string $position The possible options are: 'after', 'before'. Specifies how to interpret the $targetId.
     * @param null|int $groupingId Id of the grouping entity. If it not passed the $targetId will be used in a sub-query
     * to derived its value. It has sense only if $this->grpField is not null.
     * @return bool|int Returns false if it's not possible to derive a sort value,
     * thus sort field for all items following $targetId (including $targetId itself if $position == 'before')
     * must be incremented by $this->sortGap value.
     * @throws \Exception
     */
    public function deriveSortVal($targetId, $position = 'after', $groupingId = null)
    {
        if (!$position || !in_array($position, ['after', 'before'])) {
            throw new \Exception('You must specify correct position: "after" or "before".');
        }

        $sort = false;
        if ($this->grpField) {
            if ($groupingId) {
                $subQueryGroupId = $groupingId;
            }
            else {
                $subQueryGroupId = (new Query())
                    ->select($this->grpField)
                    ->from($this->targetTable)
                    ->where([$this->pkField => $targetId]);
            }
        }

        $subQuery = (new Query())
            ->select($this->srtField)
            ->from($this->targetTable)
            ->where([
                'and',
                $this->grpField ? ['=', $this->grpField, $subQueryGroupId] : [],
                [$this->pkField => $targetId]
            ]);

        $query = (new Query())
            ->select($this->srtField)
            ->from($this->targetTable)
            ->where([
                'and',
                $this->grpField ? ['=', $this->grpField, $subQueryGroupId] : [],
                $position == 'after' ? ['>=', $this->srtField, $subQuery] : ['<=', $this->srtField, $subQuery],
                $this->deletedField ? ['=', $this->deletedField, false] : []
            ]);

        $query->orderBy($position == 'after' ? $this->srtField.' ASC' : $this->srtField.' DESC');
        $query->limit(2);

        $result = $query->all();
        $result = array_values($result);

        if (!count($result)) {
            throw new \Exception(
                "Record [ $targetId ] with $this->grpField [ $groupingId ] to calculate a sort value was not found."
            );
        }

        if (count($result) == 2) {
            $sort = ceil(($result[0][$this->srtField] + $result[1][$this->srtField]) / 2);
            if ($sort == $result[0][$this->srtField] || $sort == $result[1][$this->srtField]) $sort = false;
        }
        else if (count($result) == 1) {
            $sort = $position == 'after' ?
                ceil($result[0][$this->srtField] + $this->sortGap) : ceil($result[0][$this->srtField] / 2);
            if ($sort == $result[0][$this->srtField]) $sort = false;
        }

        return $sort;
    }

    /**
     * Returns an id of the item before or after $targetId, depending on $position.
     * Returns false if $targetId does not exist or it's the first or last item in the list.
     *
     * @param int $targetId
     * @param string $position The possible options are: 'after', 'before'. Specifies how to interpret the $targetId.
     * @param null|int $groupingId Id of the grouping entity. If it not passed the $targetId will be used in a sub-query
     * to derived its value. It has sense only if $this->grpField is not null.
     * @return array|bool
     * @throws \Exception
     */
    public function getPk($targetId, $position, $groupingId = null)
    {
        if (!$position || !in_array($position, ['after', 'before'])) {
            throw new \Exception('You must specify correct position: "after" or "before".');
        }

        if ($this->grpField) {
            if ($groupingId) {
                $subQueryGroupId = $groupingId;
            }
            else {
                $subQueryGroupId = (new Query())
                    ->select($this->grpField)
                    ->from($this->targetTable)
                    ->where([$this->pkField => $targetId]);
            }
        }

        $subQuery = (new Query())
            ->select($this->srtField)
            ->from($this->targetTable)
            ->where([
                'and',
                $this->grpField ? ['=', $this->grpField, $subQueryGroupId] : [],
                [$this->pkField => $targetId]
            ]);

        $query = (new Query())
            ->select([$this->pkField])
            ->from($this->targetTable)
            ->where([
                'and',
                $this->grpField ? ['=', $this->grpField, $subQueryGroupId] : [],
                $position == 'after' ? ['>', $this->srtField, $subQuery] : ['<', $this->srtField, $subQuery]
            ]);

        $query->orderBy($position == 'after' ? $this->srtField.' ASC' : $this->srtField.' DESC');
        $query->limit(1);

        $result = $query->scalar();

        return $result;
    }

     /**
     * @param int $afterId
     * @param bool $includeMe True to update $afterId itself along with another rows.
     * @param null|int $groupingId Id of the grouping entity. If it not passed the $targetId will be used in a sub-query
     * to derived its value. It has sense only if $this->grpField is not null.
     * @return int Number of rows affected by the execution.
     */
    protected function rebuildSortAfter($afterId, $includeMe = false, $groupingId = null)
    {
        $subQuerySortVal = (new Query())
            ->select($this->srtField)
            ->from($this->targetTable)
            ->where([$this->pkField => $afterId]);

        if ($this->grpField) {
            if ($groupingId) {
                $subQueryGroupId = $groupingId;
            }
            else {
                $subQueryGroupId = (new Query())
                    ->select($this->grpField)
                    ->from($this->targetTable)
                    ->where([$this->pkField => $afterId]);
            }
        }

        return \Yii::$app->db->createCommand()
            ->update(
                $this->targetTable,
                [$this->srtField => new Expression("{$this->srtField} + {$this->sortGap}")],
                [
                    'and',
                    [$includeMe ? '>=' : '>', $this->srtField, $subQuerySortVal],
                    $this->grpField ? [$this->grpField => $subQueryGroupId] : []
                ]
            )
            ->execute();
    }
}