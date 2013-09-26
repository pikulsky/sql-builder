<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @package Aura.Sql
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Sql\Query\Trait;

/**
 *
 * A trait for common UPDATE functionality.
 *
 * @package Aura.Sql
 *
 */
class Update extends AbstractQuery
{
    use ValuesTrait;
    use WhereTrait;

    /**
     *
     * The table to update.
     *
     * @var string
     *
     */
    protected $table;

    /**
     *
     * Sets the table to update.
     *
     * @param string $table The table to update.
     *
     * @return $this
     *
     */
    public function table($table)
    {
        $this->table = $this->quoteName($table);
        return $this;
    }
    
    protected function build()
    {
        return "UPDATE {$this->table}" . PHP_EOL
             . $this->buildValuesForUpdate()
             . $this->buildWhere()
             . PHP_EOL;
    }
}