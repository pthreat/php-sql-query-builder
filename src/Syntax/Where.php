<?php
/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 6/3/14
 * Time: 12:07 AM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Sql\QueryBuilder\Syntax;

use NilPortugues\Sql\QueryBuilder\Manipulation\QueryException;
use NilPortugues\Sql\QueryBuilder\Manipulation\QueryFactory;
use NilPortugues\Sql\QueryBuilder\Manipulation\QueryInterface;
use NilPortugues\Sql\QueryBuilder\Manipulation\Select;

/**
 * Class Where.
 */
class Where
{
    const OPERATOR_GREATER_THAN_OR_EQUAL = '>=';
    const OPERATOR_GREATER_THAN = '>';
    const OPERATOR_LESS_THAN_OR_EQUAL = '<=';
    const OPERATOR_LESS_THAN = '<';
    const OPERATOR_LIKE = 'LIKE';
    const OPERATOR_NOT_LIKE = 'NOT LIKE';
    const OPERATOR_EQUAL = '=';
    const OPERATOR_NOT_EQUAL = '<>';
    const CONJUNCTION_AND = 'AND';
    const CONJUNCTION_AND_NOT = 'AND NOT';
    const CONJUNCTION_OR = 'OR';
    const CONJUNCTION_OR_NOT = 'OR NOT';
    const CONJUNCTION_EXISTS = 'EXISTS';
    const CONJUNCTION_NOT_EXISTS = 'NOT EXISTS';

    /**
     * @var array
     */
    protected $comparisons = [];

    /**
     * @var array
     */
    protected $betweens = [];

    /**
     * @var array
     */
    protected $isNull = [];

    /**
     * @var array
     */
    protected $isNotNull = [];

    /**
     * @var array
     */
    protected $booleans = [];

    /**
     * @var array
     */
    protected $match = [];

    /**
     * @var array
     */
    protected $ins = [];

    /**
     * @var array
     */
    protected $notIns = [];

    /**
     * @var array
     */
    protected $subWheres = [];

    /**
     * @var string
     */
    protected $conjunction = self::CONJUNCTION_AND;

    /**
     * @var QueryInterface
     */
    protected $query;

    /**
     * @var Table
     */
    protected $table;

    /**
     * @var array
     */
    protected $exists = [];

    /**
     * @var array
     */
    protected $notExists = [];

    /**
     * @var array
     */
    protected $notBetweens = [];

    /**
     * @param QueryInterface $query
     */
    public function __construct(QueryInterface $query)
    {
        $this->query = $query;
    }

    /**
     * Deep copy for nested references.
     *
     * @return mixed
     */
    public function __clone()
    {
        return \unserialize(\serialize($this));
    }

    public function isEmpty() : bool
    {
        $empty = \array_merge(
            $this->comparisons,
            $this->booleans,
            $this->betweens,
            $this->isNotNull,
            $this->isNull,
            $this->ins,
            $this->notIns,
            $this->subWheres,
            $this->exists
        );

        return 0 === \count($empty);
    }

    public function getConjunction() : string
    {
        return $this->conjunction;
    }

    public function conjunction($operator) : Where
    {
        if (false === \in_array(
                $operator,
                [self::CONJUNCTION_AND, self::CONJUNCTION_OR, self::CONJUNCTION_OR_NOT, self::CONJUNCTION_AND_NOT]
            )
        ) {
            throw new QueryException(
                "Invalid conjunction specified, must be one of AND or OR, but '".$operator."' was found."
            );
        }
        $this->conjunction = $operator;

        return $this;
    }

    public function getSubWheres() : array
    {
        return $this->subWheres;
    }

    public function subWhere($operator = 'OR') : Where
    {
        $filter = QueryFactory::createWhere($this->query);
        $filter->conjunction($operator);
        $filter->setTable($this->getTable());

        $this->subWheres[] = $filter;

        return $filter;
    }

    public function getTable() : Table
    {
        return $this->query->getTable();
    }

    /**
     * Used for subWhere query building.
     *
     * @param Table|string $table string
     */
    public function setTable($table) : Where
    {
        $this->table = $table;

        return $this;
    }

    /**
     * equals alias.
     */
    public function eq($column, $value) : Where
    {
        return $this->equals($column, $value);
    }

    public function equals($column, $value) : Where
    {
        return $this->compare($column, $value, self::OPERATOR_EQUAL);
    }

    protected function compare($column, $value, $operator) : Where
    {
        $column = $this->prepareColumn($column);

        $this->comparisons[] = [
            'subject' => $column,
            'conjunction' => $operator,
            'target' => $value,
        ];

        return $this;
    }

    /**
     * @param $column
     *
     * @return Column|Select
     */
    protected function prepareColumn($column)
    {
        //This condition handles the "Select as a a column" special case.
        //or when compare column is customized.
        if ($column instanceof Select || $column instanceof Column) {
            return $column;
        }

        $newColumn = [$column];

        return SyntaxFactory::createColumn($newColumn, $this->getTable());
    }

    public function notEquals($column, $value) : Where
    {
        return $this->compare($column, $value, self::OPERATOR_NOT_EQUAL);
    }

    public function greaterThan($column, $value) : Where
    {
        return $this->compare($column, $value, self::OPERATOR_GREATER_THAN);
    }

    public function greaterThanOrEqual($column, $value) : Where
    {
        return $this->compare($column, $value, self::OPERATOR_GREATER_THAN_OR_EQUAL);
    }

    public function lessThan($column, $value) : Where
    {
        return $this->compare($column, $value, self::OPERATOR_LESS_THAN);
    }

    public function lessThanOrEqual($column, $value) : Where
    {
        return $this->compare($column, $value, self::OPERATOR_LESS_THAN_OR_EQUAL);
    }

    public function like($column, $value) : Where
    {
        return $this->compare($column, $value, self::OPERATOR_LIKE);
    }

    public function notLike($column, $value) : Where
    {
        return $this->compare($column, $value, self::OPERATOR_NOT_LIKE);
    }

    public function match(array $columns, array $values) : Where
    {
        return $this->genericMatch($columns, $values, 'natural');
    }

    protected function genericMatch(array &$columns, array &$values, $mode) : Where
    {
        $this->match[] = [
            'columns' => $columns,
            'values' => $values,
            'mode' => $mode,
        ];

        return $this;
    }

    public function asLiteral($literal) : Where
    {
        $this->comparisons[] = $literal;

        return $this;
    }

    public function matchBoolean(array $columns, array $values) : Where
    {
        return $this->genericMatch($columns, $values, 'boolean');
    }

    public function matchWithQueryExpansion(array $columns, array $values) : Where
    {
        return $this->genericMatch($columns, $values, 'query_expansion');
    }

    public function in($column, array $values) : Where
    {
        $this->ins[$column] = $values;

        return $this;
    }

    public function notIn($column, array $values) : Where
    {
        $this->notIns[$column] = $values;

        return $this;
    }

    public function between($column, $a, $b) : Where
    {
        $prepColumn = $this->prepareColumn($column);
        $this->betweens[] = ['subject' => $prepColumn, 'a' => $a, 'b' => $b];

        return $this;
    }

    public function notBetween($column, $a, $b) : Where
    {
        $column = $this->prepareColumn($column);
        $this->notBetweens[] = ['subject' => $column, 'a' => $a, 'b' => $b];

        return $this;
    }

    public function isNull($column) : Where
    {
        $column = $this->prepareColumn($column);
        $this->isNull[] = ['subject' => $column];

        return $this;
    }

    public function isNotNull($column) : Where
    {
        $column = $this->prepareColumn($column);
        $this->isNotNull[] = ['subject' => $column];

        return $this;
    }

    public function addBitClause($column, $value) : Where
    {
        $column = $this->prepareColumn($column);
        $this->booleans[] = ['subject' => $column, 'value' => $value];

        return $this;
    }

    public function exists(Select $select) : Where
    {
        $this->exists[] = $select;

        return $this;
    }

    public function getExists() : array
    {
        return $this->exists;
    }

    public function notExists(Select $select) : Where
    {
        $this->notExists[] = $select;

        return $this;
    }

    public function getNotExists() : array

    {
        return $this->notExists;
    }

    public function getMatches() : array
    {
        return $this->match;
    }

    public function getIns() : array
    {
        return $this->ins;
    }

    public function getNotIns() : array
    {
        return $this->notIns;
    }

    public function getBetweens() : array
    {
        return $this->betweens;
    }

    public function getNotBetweens() : array
    {
        return $this->notBetweens;
    }

    public function getBooleans() : array
    {
        return $this->booleans;
    }

    /**
     * @return array
     */
    public function getComparisons() : array
    {
        return $this->comparisons;
    }

    public function getNotNull() : array
    {
        return $this->isNotNull;
    }

    public function getNull() : array
    {
        return $this->isNull;
    }

    public function end() : QueryInterface
    {
       return $this->query;
    }
}
