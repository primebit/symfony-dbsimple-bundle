<?php
namespace ToolsBundle\DbSimple;

/**
 * Database class for PostgreSQL.
 */
class DbsPgsql extends DbSimple
{

    var $DbSimple_Postgresql_USE_NATIVE_PHOLDERS = null;
    var $prepareCache = [];
    var $link;

    /**
     * Connect to PostgresSQL.
     *
     * @param $host
     * @param $port
     * @param $dbname
     * @param $user
     * @param $pass
     */
    public function __construct($host, $port, $dbname, $user, $pass)
    {
        if (!is_callable('pg_connect')) {
            return $this->_setLastError("-1", "PostgreSQL extension is not loaded", "pg_connect");
        }

        $this->DbSimple_Postgresql_USE_NATIVE_PHOLDERS = function_exists('pg_prepare');

        $this->link = pg_connect("host={$host} port={$port} dbname={$dbname} user={$user} password={$pass}");
        $this->_resetLastError();
    }


    public function _performEscape($s, $isIdent = false)
    {
        if (!$isIdent) {
            return "E'" . pg_escape_string($this->link, $s) . "'";
        } else {
            return '"' . str_replace('"', '_', $s) . '"';
        }
    }


    public function _performTransaction($parameters = null)
    {
        return $this->query('BEGIN');
    }


    public function _performNewBlob($blobid = null)
    {
        //        return new DbSimple_Postgresql_Blob($this, $blobid);
    }


    public function _performGetBlobFieldNames($result)
    {
        //        $blobFields = array();
        //        for ($i = pg_num_fields($result) - 1; $i >= 0; $i--) {
        //            $type = pg_field_type($result, $i);
        //            if (strpos($type, "BLOB") !== false) {
        //                $blobFields[] = pg_field_name($result, $i);
        //            }
        //        }
        //        return $blobFields;
    }

    // TODO: Real PostgreSQL escape
    public function _performGetPlaceholderIgnoreRe()
    {
        return '
            "   (?> [^"\\\\]+|\\\\"|\\\\)*    "   |
            \'  (?> [^\'\\\\]+|\\\\\'|\\\\)* \'   |
            /\* .*?                          \*/      # comments
        ';
    }

    public function _performGetNativePlaceholderMarker($n)
    {
        // PostgreSQL uses specific placeholders such as $1, $2, etc.
        return '$' . ($n + 1);
    }

    public function _performCommit()
    {
        return $this->query('COMMIT');
    }


    public function _performRollback()
    {
        return $this->query('ROLLBACK');
    }


    public function _performTransformQuery(&$queryMain, $how)
    {

        // If we also need to calculate total number of found rows...
        switch ($how) {
            // Prepare total calculation (if possible)
            case 'CALC_TOTAL':
                // Not possible
                return true;

            // Perform total calculation.
            case 'GET_TOTAL':
                // TODO: GROUP BY ... -> COUNT(DISTINCT ...)
                $re = '/^
                    (?> -- [^\r\n]* | \s+)*
                    (\s* SELECT \s+)                                             #1
                    (.*?)                                                        #2
                    (\s+ FROM \s+ .*?)                                           #3
                        ((?:\s+ ORDER \s+ BY \s+ .*?)?)                          #4
                        ((?:\s+ LIMIT \s+ \S+ \s* (?: OFFSET \s* \S+ \s*)? )?)  #5
                $/six';
                $m = null;
                if (preg_match($re, $queryMain[0], $m)) {
                    $queryMain[0] = $m[1] . $this->_fieldList2Count($m[2]) . " AS C" . $m[3];
                    $skipTail = substr_count($m[4] . $m[5], '?');
                    if ($skipTail) {
                        array_splice($queryMain, -$skipTail);
                    }
                }
                return true;
        }

        return false;
    }


    public function _performQuery($queryMain)
    {
        //        $this->_lastQuery = $queryMain;
        $isInsert = preg_match('/^\s* INSERT \s+/six', $queryMain[0]);

        $this->_expandPlaceholders($queryMain, false);
        $result = pg_query($this->link, $queryMain[0]);
        //}

        if ($result === false) {
            return $this->_setDbError($queryMain);
        }
        if (!pg_num_fields($result)) {
            if ($isInsert) {
                return pg_last_oid($result);
            }
            // Non-SELECT queries return number of affected rows, SELECT - resource.
            return pg_affected_rows($result);
        }
        return $result;
    }


    public function _performFetch($result)
    {
        $row = pg_fetch_assoc($result);
        //        if (pg_last_error($this->link)) {
        //            return $this->_setDbError($this->_lastQuery);
        //        }
        return $row;
    }


    public function _setDbError($query)
    {
        return $this->_setLastError(null, $this->link ? pg_last_error($this->link) :
            (is_array($query) ? "Connection is not established" : $query), $query);
    }

    public function _getVersion()
    {
    }
}