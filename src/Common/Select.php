<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\AbstractQuery;
use Aura\SqlQuery\Exception;

/**
 *
 * An object for SELECT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Select extends AbstractQuery implements SelectInterface
{
    /**
     *
     * An array of union SELECT statements.
     *
     * @var array
     *
     */
    protected $union = array();

    /**
     *
     * Is this a SELECT FOR UPDATE?
     *
     * @var
     *
     */
    protected $for_update = false;

    /**
     *
     * The columns to be selected.
     *
     * @var array
     *
     */
    protected $cols = array();

    /**
     *
     * Select from these tables; includes JOIN clauses.
     *
     * @var array
     *
     */
    protected $from = array();

    /**
     *
     * The current key in the `$from` array.
     *
     * @var int
     *
     */
    protected $from_key = -1;

    /**
     *
     * GROUP BY these columns.
     *
     * @var array
     *
     */
    protected $group_by = array();

    /**
     *
     * The list of HAVING conditions.
     *
     * @var array
     *
     */
    protected $having = array();

    /**
     *
     * The number of rows per page.
     *
     * @var int
     *
     */
    protected $paging = 10;

    /**
     *
     * Tracks table references to avoid duplicate identifiers.
     *
     * @var array
     *
     */
    protected $table_refs = array();

    /**
     *
     * Returns this query object as an SQL statement string.
     *
     * @return string An SQL statement string.
     *
     */
    public function getStatement()
    {
        $union = '';
        if ($this->union) {
            $union = implode(PHP_EOL, $this->union) . PHP_EOL;
        }
        return $union . $this->build();
    }

    /**
     *
     * Sets the number of rows per page.
     *
     * @param int $paging The number of rows to page at.
     *
     * @return self
     *
     */
    public function setPaging($paging)
    {
        $this->paging = (int) $paging;
        return $this;
    }

    /**
     *
     * Gets the number of rows per page.
     *
     * @return int The number of rows per page.
     *
     */
    public function getPaging()
    {
        return $this->paging;
    }

    /**
     *
     * Makes the select FOR UPDATE (or not).
     *
     * @param bool $enable Whether or not the SELECT is FOR UPDATE (default
     * true).
     *
     * @return self
     *
     */
    public function forUpdate($enable = true)
    {
        $this->for_update = (bool) $enable;
    }

    /**
     *
     * Makes the select DISTINCT (or not).
     *
     * @param bool $enable Whether or not the SELECT is DISTINCT (default
     * true).
     *
     * @return self
     *
     */
    public function distinct($enable = true)
    {
        $this->setFlag('DISTINCT', $enable);
        return $this;
    }

    /**
     *
     * Adds columns to the query.
     *
     * Multiple calls to cols() will append to the list of columns, not
     * overwrite the previous columns.
     *
     * @param array $cols The column(s) to add to the query. The elements can be
     * any mix of these: `array("col", "col AS alias", "col" => "alias")`
     *
     * @return self
     *
     */
    public function cols(array $cols)
    {
        foreach ($cols as $key => $val) {
            $this->addCol($key, $val);
        }
        return $this;
    }

    /**
     *
     * Adds a column and alias to the columns to be selected.
     *
     * @param mixed $key If an integer, ignored. Otherwise, the column to be
     * added.
     *
     * @param mixed $val If $key was an integer, the column to be added;
     * otherwise, the column alias.
     *
     * @return null
     *
     */
    protected function addCol($key, $val)
    {
        if (is_string($key)) {
            // [col => alias]
            $this->cols[$val] = $key;
        } else {
            $this->addColWithAlias($val);
        }
    }

    /**
     *
     * Adds a column with an alias to the columns to be selected.
     *
     * @param string $spec The column specification: "col alias",
     * "col AS alias", or something else entirely.
     *
     * @return null
     *
     */
    protected function addColWithAlias($spec)
    {
        $parts = explode(' ', $spec);
        $count = count($parts);
        if ($count == 2) {
            // "col alias"
            $this->cols[$parts[1]] = $parts[0];
        } elseif ($count == 3 && strtoupper($parts[1]) == 'AS') {
            // "col AS alias"
            $this->cols[$parts[2]] = $parts[0];
        } else {
            // no recognized alias
            $this->cols[] = $spec;
        }
    }

    /**
     *
     * Tracks table references.
     *
     * @param string $type FROM, JOIN, etc.
     *
     * @param string $spec The table and alias name.
     *
     * @return null
     *
     * @throws Exception when the reference has already been used.
     *
     */
    protected function addTableRef($type, $spec)
    {
        $name = $spec;

        $pos = strripos($name, ' AS ');
        if ($pos !== false) {
            $name = trim(substr($name, $pos + 4));
        }

        if (isset($this->table_refs[$name])) {
            $used = $this->table_refs[$name];
            throw new Exception("Cannot reference '$type $spec' after '$used'");
        }

        $this->table_refs[$name] = "$type $spec";
    }

    /**
     *
     * Adds a FROM element to the query; quotes the table name automatically.
     *
     * @param string $spec The table specification; "foo" or "foo AS bar".
     *
     * @return self
     *
     */
    public function from($spec)
    {
        $this->addTableRef('FROM', $spec);
        return $this->addFrom($this->quoter->quoteName($spec));
    }

    /**
     *
     * Adds a raw unquoted FROM element to the query; useful for adding FROM
     * elements that are functions.
     *
     * @param string $spec The table specification, e.g. "function_name()".
     *
     * @return self
     *
     */
    public function fromRaw($spec)
    {
        $this->addTableRef('FROM', $spec);
        return $this->addFrom($spec);
    }

    /**
     *
     * Adds to the $from property and increments the key count.
     *
     * @param string $spec The table specification.
     *
     * @return self
     *
     */
    protected function addFrom($spec)
    {
        $this->from[] = array($spec);
        $this->from_key ++;
        return $this;
    }

    /**
     *
     * Adds an aliased sub-select to the query.
     *
     * @param string|Select $spec If a Select object, use as the sub-select;
     * if a string, the sub-select string.
     *
     * @param string $name The alias name for the sub-select.
     *
     * @return self
     *
     */
    public function fromSubSelect($spec, $name)
    {
        $this->addTableRef('FROM (SELECT ...) AS', $name);
        $spec = $this->subSelect($spec, '        ');
        $name = $this->quoter->quoteName($name);
        $this->from[] = array("({$spec}    ) AS $name");
        $this->from_key ++;
        return $this;
    }

    /**
     *
     * Formats a sub-SELECT statement, binding values from a Select object as
     * needed.
     *
     * @param string|SelectInterface $spec A sub-SELECT specification.
     *
     * @param string $indent Indent each line with this string.
     *
     * @return string The sub-SELECT string.
     *
     */
    protected function subSelect($spec, $indent)
    {
        if ($spec instanceof SelectInterface) {
            $this->bindValues($spec->getBindValues());
        }

        return PHP_EOL . $indent
            . ltrim(preg_replace('/^/m', $indent, (string) $spec))
            . PHP_EOL;
    }

    /**
     *
     * Adds a JOIN table and columns to the query.
     *
     * @param string $join The join type: inner, left, natural, etc.
     *
     * @param string $spec The table specification; "foo" or "foo AS bar".
     *
     * @param string $cond Join on this condition.
     *
     * @param array $bind Values to bind to ?-placeholders in the condition.
     *
     * @return self
     *
     * @throws Exception
     *
     */
    public function join($join, $spec, $cond = null, array $bind = array())
    {
        if (! $this->from) {
            throw new Exception('Cannot join() without from() first.');
        }

        $join = strtoupper(ltrim("$join JOIN"));
        $this->addTableRef($join, $spec);

        $spec = $this->quoter->quoteName($spec);
        $cond = $this->fixJoinCondition($cond, $bind);
        $this->from[$this->from_key][] = rtrim("$join $spec $cond");
        return $this;
    }

    /**
     *
     * Fixes a JOIN condition to quote names in the condition and prefix it
     * with a condition type ('ON' is the default and 'USING' is recognized).
     *
     * @param string $cond Join on this condition.
     *
     * @param array $bind Values to bind to ?-placeholders in the condition.
     *
     * @return string
     *
     */
    protected function fixJoinCondition($cond, array $bind)
    {
        if (! $cond) {
            return;
        }

        $cond = $this->quoter->quoteNamesIn($cond);
        $cond = $this->rebuildCondAndBindValues($cond, $bind);

        if (strtoupper(substr(ltrim($cond), 0, 3)) == 'ON ') {
            return $cond;
        }

        if (strtoupper(substr(ltrim($cond), 0, 6)) == 'USING ') {
            return $cond;
        }

        return 'ON ' . $cond;
    }

    /**
     *
     * Adds a INNER JOIN table and columns to the query.
     *
     * @param string $spec The table specification; "foo" or "foo AS bar".
     *
     * @param string $cond Join on this condition.
     *
     * @param array $bind Values to bind to ?-placeholders in the condition.
     *
     * @return self
     *
     * @throws Exception
     *
     */
    public function innerJoin($spec, $cond = null, array $bind = array())
    {
        return $this->join('INNER', $spec, $cond, $bind);
    }

    /**
     *
     * Adds a LEFT JOIN table and columns to the query.
     *
     * @param string $spec The table specification; "foo" or "foo AS bar".
     *
     * @param string $cond Join on this condition.
     *
     * @param array $bind Values to bind to ?-placeholders in the condition.
     *
     * @return self
     *
     * @throws Exception
     *
     */
    public function leftJoin($spec, $cond = null, array $bind = array())
    {
        return $this->join('LEFT', $spec, $cond, $bind);
    }

    /**
     *
     * Adds a JOIN to an aliased subselect and columns to the query.
     *
     * @param string $join The join type: inner, left, natural, etc.
     *
     * @param string|Select $spec If a Select
     * object, use as the sub-select; if a string, the sub-select
     * command string.
     *
     * @param string $name The alias name for the sub-select.
     *
     * @param string $cond Join on this condition.
     *
     * @param array $bind Values to bind to ?-placeholders in the condition.
     *
     * @return self
     *
     * @throws Exception
     *
     */
    public function joinSubSelect($join, $spec, $name, $cond = null, array $bind = array())
    {
        if (! $this->from) {
            throw new Exception('Cannot join() without from() first.');
        }

        $join = strtoupper(ltrim("$join JOIN"));
        $this->addTableRef("$join (SELECT ...) AS", $name);

        $spec = $this->subSelect($spec, '            ');
        $name = $this->quoter->quoteName($name);
        $cond = $this->fixJoinCondition($cond, $bind);

        $text = rtrim("$join ($spec        ) AS $name $cond");
        $this->from[$this->from_key][] = '        ' . $text ;
        return $this;
    }

    /**
     *
     * Adds grouping to the query.
     *
     * @param array $spec The column(s) to group by.
     *
     * @return self
     *
     */
    public function groupBy(array $spec)
    {
        foreach ($spec as $col) {
            $this->group_by[] = $this->quoter->quoteNamesIn($col);
        }
        return $this;
    }

    /**
     *
     * Adds a HAVING condition to the query by AND. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The HAVING condition.
     *
     * @return self
     *
     */
    public function having($cond)
    {
        $this->addClauseCondWithBind('having', 'AND', func_get_args());
        return $this;
    }

    /**
     *
     * Adds a HAVING condition to the query by AND. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The HAVING condition.
     *
     * @return self
     *
     * @see having()
     *
     */
    public function orHaving($cond)
    {
        $this->addClauseCondWithBind('having', 'OR', func_get_args());
        return $this;
    }

    /**
     *
     * Sets the limit and count by page number.
     *
     * @param int $page Limit results to this page number.
     *
     * @return self
     *
     */
    public function page($page)
    {
        // reset the count and offset
        $this->limit  = 0;
        $this->offset = 0;

        // determine the count and offset from the page number
        $page = (int) $page;
        if ($page > 0) {
            $this->limit  = $this->paging;
            $this->offset = $this->paging * ($page - 1);
        }

        // done
        return $this;
    }

    /**
     *
     * Takes the current select properties and retains them, then sets
     * UNION for the next set of properties.
     *
     * @return self
     *
     */
    public function union()
    {
        $this->union[] = $this->build() . PHP_EOL . 'UNION';
        $this->reset();
        return $this;
    }

    /**
     *
     * Takes the current select properties and retains them, then sets
     * UNION ALL for the next set of properties.
     *
     * @return self
     *
     */
    public function unionAll()
    {
        $this->union[] = $this->build() . PHP_EOL . 'UNION ALL';
        $this->reset();
        return $this;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function getOffset()
    {
        return $this->offset;
    }

    /**
     *
     * Clears the current select properties; generally used after adding a
     * union.
     *
     * @return null
     *
     */
    protected function reset()
    {
        $this->resetFlags();
        $this->cols       = array();
        $this->from       = array();
        $this->from_key   = -1;
        $this->where      = array();
        $this->group_by   = array();
        $this->having     = array();
        $this->order_by   = array();
        $this->limit      = 0;
        $this->offset     = 0;
        $this->for_update = false;
    }

    /**
     *
     * Builds this query object into a string.
     *
     * @return string
     *
     */
    protected function build()
    {
        return 'SELECT'
            . $this->buildFlags()
            . $this->buildCols()
            . $this->buildFrom() // includes JOIN
            . $this->buildWhere()
            . $this->buildGroupBy()
            . $this->buildHaving()
            . $this->buildOrderBy()
            . $this->buildLimit()
            . $this->buildForUpdate();
    }

    /**
     *
     * Builds the columns clause.
     *
     * @return string
     *
     * @throws Exception when there are no columns in the SELECT.
     *
     */
    protected function buildCols()
    {
        if (! $this->cols) {
            throw new Exception('No columns in the SELECT.');
        }

        $cols = array();
        foreach ($this->cols as $key => $val) {
            if (is_int($key)) {
                $cols[] = $this->quoter->quoteNamesIn($val);
            } else {
                $cols[] = $this->quoter->quoteNamesIn("$val AS $key");
            }
        }

        return $this->indentCsv($cols);
    }

    /**
     *
     * Builds the FROM clause.
     *
     * @return string
     *
     */
    protected function buildFrom()
    {
        if (! $this->from) {
            return ''; // not applicable
        }

        $refs = array();
        foreach ($this->from as $from) {
            $refs[] = implode(PHP_EOL, $from);
        }
        return PHP_EOL . 'FROM' . $this->indentCsv($refs);
    }

    /**
     *
     * Builds the GROUP BY clause.
     *
     * @return string
     *
     */
    protected function buildGroupBy()
    {
        if (! $this->group_by) {
            return ''; // not applicable
        }

        return PHP_EOL . 'GROUP BY' . $this->indentCsv($this->group_by);
    }

    /**
     *
     * Builds the HAVING clause.
     *
     * @return string
     *
     */
    protected function buildHaving()
    {
        if (! $this->having) {
            return ''; // not applicable
        }

        return PHP_EOL . 'HAVING' . $this->indent($this->having);
    }

    /**
     *
     * Builds the FOR UPDATE clause.
     *
     * @return string
     *
     */
    protected function buildForUpdate()
    {
        if (! $this->for_update) {
            return ''; // not applicable
        }

        return PHP_EOL . 'FOR UPDATE';
    }

    /**
     *
     * Adds a WHERE condition to the query by AND. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The WHERE condition.
     * @param mixed ...$bind arguments to bind to placeholders
     *
     * @return self
     *
     */
    public function where($cond)
    {
        $this->addWhere('AND', func_get_args());
        return $this;
    }

    /**
     *
     * Adds a WHERE condition to the query by OR. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The WHERE condition.
     * @param mixed ...$bind arguments to bind to placeholders
     *
     * @return self
     *
     * @see where()
     *
     */
    public function orWhere($cond)
    {
        $this->addWhere('OR', func_get_args());
        return $this;
    }

    /**
     *
     * Sets a limit count on the query.
     *
     * @param int $limit The number of rows to select.
     *
     * @return self
     *
     */
    public function limit($limit)
    {
        $this->limit = (int) $limit;
        return $this;
    }

    /**
     *
     * Sets a limit offset on the query.
     *
     * @param int $offset Start returning after this many rows.
     *
     * @return self
     *
     */
    public function offset($offset)
    {
        $this->offset = (int) $offset;
        return $this;
    }

    /**
     *
     * Adds a column order to the query.
     *
     * @param array $spec The columns and direction to order by.
     *
     * @return self
     *
     */
    public function orderBy(array $spec)
    {
        return $this->addOrderBy($spec);
    }
}
