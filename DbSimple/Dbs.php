<?php
namespace ToolsBundle\DbSimple;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOStatement;

class Dbs extends DbSimple
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Return pure Connection
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    protected function _performEscape($s, $isIdent = false)
    {
        if (!$isIdent) {
            return $this->connection->quote($s);
        } else {
            return '"' . str_replace('`', '``', $s) . '"';
        }
    }

    protected function _performNewBlob($blobid = null)
    {
    }

    protected function _performGetBlobFieldNames($result)
    {
    }

    protected function _performGetPlaceholderIgnoreRe()
    {
        return '
            "   (?> [^"\\\\]+|\\\\"|\\\\)*    "   |
            \'  (?> [^\'\\\\]+|\\\\\'|\\\\)* \'   |
            `   (?> [^`]+ | ``)*              `   |   # backticks
            /\* .*?                          \*/      # comments
        ';
    }

    protected function _performTransaction($parameters = null)
    {
        $this->connection->beginTransaction();
    }

    protected function _performCommit()
    {
        $this->connection->commit();
    }

    protected function _performRollback()
    {
        $this->connection->rollBack();
    }

    protected function _performTransformQuery(&$queryMain, $how)
    {
        // If we also need to calculate total number of found rows...
        switch ($how) {
            // Prepare total calculation (if possible)
            case 'CALC_TOTAL':
                $m = null;
                if (preg_match('/^(\s* SELECT)(.*)/six', $queryMain[0], $m)) {
                    $queryMain[0] = $m[1] . ' SQL_CALC_FOUND_ROWS' . $m[2];
                }

                return true;

            // Perform total calculation.
            case 'GET_TOTAL':
                // Built-in calculation available?
                $queryMain = array('SELECT FOUND_ROWS()');

                return true;
        }

        return false;
    }

    protected function _performQuery($queryMain)
    {
        $this->_expandPlaceholders($queryMain, false);

        $result = $this->connection->query($queryMain[0]);

        if (!is_resource($result)) {

            if (preg_match('/^\s* INSERT \s+/six', $queryMain[0])) {
//                return $result->rowCount();
            } elseif (preg_match('/^\s* UPDATE \s+/six', $queryMain[0])) {
                return $result->rowCount();
            } elseif (preg_match('/TRUNCATE\sTABLE/', $queryMain[0])) {
                return true;
            } elseif (strpos($queryMain[0], 'SET') === 0) {
                return true;
            } elseif (preg_match('/RENAME/', $queryMain[0])) {
                return true;
            } elseif (preg_match('/DELETE/', $queryMain[0])) {
                return $result->rowCount();
            }
        }

        return $result;
    }

    /** @var PDOStatement $result
     *
     * @return PDOStatement|mixed
     */
    protected function _performFetch($result)
    {
        $result = $result->fetch(\PDO::FETCH_ASSOC);

        return $result;
    }
}