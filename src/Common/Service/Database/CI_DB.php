<?php

use Nails\Common\Exception\Database\QueryException;

class CI_DB extends \CI_DB_query_builder
{
    /**
     * Display an error message using an exception rather than a view
     *
     * @param string $error  The error message
     * @param string $swap   Any "swap" values
     * @param bool   $native Whether to localize the message
     *
     * @return    void
     */
    public function display_error($error = '', $swap = '', $native = false)
    {
        if (is_array($error)) {
            $error = implode(PHP_EOL . PHP_EOL, $error);
        }

        throw new QueryException($error);
    }

    // --------------------------------------------------------------------------

    /**
     * Update statement
     *
     * Generates a platform-specific update string from the supplied data
     *
     * @param string $table  The table name
     * @param array  $values The update data
     *
     * @return    string
     */
    protected function _update($table, $values)
    {
        $aValues = [];
        foreach ($values as $key => $val) {
            $aValues[] = $key . ' = ' . $val;
        }

        return 'UPDATE ' . $table . ' ' . $this->_compile_join() . ' SET ' . implode(', ', $aValues)
            . $this->_compile_wh('qb_where')
            . $this->_compile_order_by()
            . ($this->qb_limit ? ' LIMIT ' . $this->qb_limit : '');
    }

    // --------------------------------------------------------------------------

    /**
     * Compiles the JOIN string
     *
     * @return string
     */
    protected function _compile_join()
    {
        $sSql = '';
        // Write the "JOIN" portion of the query
        if (count($this->qb_join) > 0) {
            $sSql .= "\n" . implode("\n", $this->qb_join);
        }
        return $sSql;
    }
}
