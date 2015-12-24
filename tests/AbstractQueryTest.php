<?php
namespace Aura\SqlQuery;

use Aura\SqlQuery\Common\Delete;
use Aura\SqlQuery\Common\Insert;
use Aura\SqlQuery\Common\Select;
use Aura\SqlQuery\Common\Update;

abstract class AbstractQueryTest extends \PHPUnit_Framework_TestCase
{
    protected $query_factory;

    protected $query_type;

    protected $db_type = 'Common';

    /**
     * @var Select|Insert|Update|Delete
     */
    protected $query;

    protected function setUp()
    {
        parent::setUp();
        $this->query_factory = new QueryFactory($this->db_type);
        $this->query = $this->newQuery();
    }

    /**
     * @return Select|Insert|Update|Delete
     */
    protected function newQuery()
    {
        $method = 'new' . $this->query_type;
        return $this->query_factory->$method();
    }

    protected function assertSameSql($expect, $actual)
    {
        // remove leading and trailing whitespace per block and line
        $expect = trim($expect);
        $expect = preg_replace('/^[ \t]*/m', '', $expect);
        $expect = preg_replace('/[ \t]*$/m', '', $expect);

        // convert "<<" and ">>" to the correct identifier quotes
        $expect = $this->requoteIdentifiers($expect);

        // remove leading and trailing whitespace per block and line
        $actual = trim($actual);
        $actual = preg_replace('/^[ \t]*/m', '', $actual);
        $actual = preg_replace('/[ \t]*$/m', '', $actual);

        // normalize line endings to be sure tests will pass on windows and mac
        $expect = preg_replace('/\r\n|\n|\r/', PHP_EOL, $expect);
        $actual = preg_replace('/\r\n|\n|\r/', PHP_EOL, $actual);

        // are they the same now?
        $this->assertSame($expect, $actual);
    }

    protected function assertSameQuery($expect, AbstractQuery $query)
    {
        $this->assertSameSql($expect, $query->__toString());
    }

    protected function requoteIdentifiers($string)
    {
        $string = str_replace('<<', $this->query->getQuoteNamePrefix(), $string);
        $string = str_replace('>>', $this->query->getQuoteNameSuffix(), $string);
        return $string;
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    public function testBindValues()
    {
        $actual = $this->query->getBindValues();
        $this->assertSame(array(), $actual);

        $expect = array('foo' => 'bar', 'baz' => 'dib');
        $this->query->bindValues($expect);
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);

        $this->query->bindValues(array('zim' => 'gir'));
        $expect = array('foo' => 'bar', 'baz' => 'dib', 'zim' => 'gir');
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }
}
