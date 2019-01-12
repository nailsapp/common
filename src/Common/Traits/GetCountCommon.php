<?php

/**
 * Implements the common getcount_common() and getCountCommonParseSort() methods
 *
 * @package     Nails
 * @subpackage  common
 * @category    traits
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Common\Traits;

use Nails\Factory;

trait GetCountCommon
{
    /**
     * This method applies the conditionals which are common across the get_*()
     * methods and the count() method.
     *
     * @param  array $aData Data passed from the calling method
     *
     * @return void
     **/
    protected function getCountCommon(array $aData = [])
    {
        //  @deprecated - searching should use the search() method, but this in place
        //  as a quick fix for loads of admin controllers
        if (!empty($aData['keywords']) && !empty($this->searchableFields)) {
            if (empty($aData['or_like'])) {
                $aData['or_like'] = [];
            }

            $sAlias = $this->getTableAlias(true);

            foreach ($this->searchableFields as $mField) {

                //  If the field is an array then search across the columns concatenated together
                if (is_array($mField)) {

                    $sMappedFields = array_map(function ($sInput) use ($sAlias) {
                        if (strpos($sInput, '.') !== false) {
                            return $sInput;
                        } else {
                            return $sAlias . $sInput;
                        }
                    }, $mField);

                    $aData['or_like'][] = ['CONCAT_WS(" ", ' . implode(',', $sMappedFields) . ')', $aData['keywords']];

                } else {
                    if (strpos($mField, '.') !== false) {
                        $aData['or_like'][] = [$mField, $aData['keywords']];
                    } else {
                        $aData['or_like'][] = [$sAlias . $mField, $aData['keywords']];
                    }
                }
            }
        }

        // --------------------------------------------------------------------------

        $this->getCountCommonCompileSelect($aData);
        $this->getCountCommonCompileFilters($aData);
        $this->getCountCommonCompileWheres($aData);
        $this->getCountCommonCompileLikes($aData);
        $this->getCountCommonCompileSort($aData);
    }

    // --------------------------------------------------------------------------

    /**
     * Compiles the select statement
     *
     * @param  array &$aData The data array
     *
     * @return void
     */
    protected function getCountCommonCompileSelect(array &$aData)
    {
        if (!empty($aData['select'])) {
            $oDb = Factory::service('Database');
            $oDb->select($aData['select']);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Compiles any active filters back into the $aData array
     *
     * @param  array &$aData The data array
     *
     * @return void
     */
    protected function getCountCommonCompileFilters(array &$aData)
    {
        /**
         * Handle filters
         * Filters are basically an easy way to modify the query's where element.
         */

        //  Checkbox Filters
        if (!empty($aData['cbFilters'])) {

            foreach ($aData['cbFilters'] as $iFilterIndex => $oFilter) {

                /**
                 * If a column isn't specified by the filter then ignore it. This is a
                 * feature/hack to allow the dev to add items to the search box but not
                 * force them to use the default filtering mechanism
                 */
                if (empty($oFilter->getColumn())) {
                    continue;
                }

                $aWhereFilter = [];
                foreach ($oFilter->getOptions() as $iOptionIndex => $oOption) {
                    if (!empty($_GET['cbF'][$iFilterIndex][$iOptionIndex])) {
                        //  Filtering is happening and the item is to be filtered
                        $aWhereFilter[] = $this->getCountCommonCompileFiltersString(
                            $oFilter->getColumn(),
                            $oOption->getValue(),
                            $oOption->isQuery()
                        );
                    } elseif (empty($_GET['cbF']) && $oOption->isSelected()) {
                        //  There's no filtering happening and the item is checked by default
                        $aWhereFilter[] = $this->getCountCommonCompileFiltersString(
                            $oFilter->getColumn(),
                            $oOption->getValue(),
                            $oOption->isQuery()
                        );
                    }
                }

                $aWhereFilter = array_filter($aWhereFilter);

                if (!empty($aWhereFilter)) {

                    if (!isset($aData['where'])) {
                        $aData['where'] = [];
                    }

                    $aData['where'][] = '(' . implode(' OR ', $aWhereFilter) . ')';
                }
            }
        }

        //  Dropdown Filters
        if (!empty($aData['ddFilters'])) {

            foreach ($aData['ddFilters'] as $iFilterIndex => $oFilter) {

                /**
                 * If a column isn't specified by the filter then ignore it. This is a
                 * feature/hack to allow the dev to add items to the search box but not
                 * force them to use the default filtering mechanism
                 */
                if (empty($oFilter->getColumn())) {
                    continue;
                }

                $sWhereFilter = [];

                //  Are we even filtering this filter?
                if (isset($_GET['ddF'][$iFilterIndex])) {

                    //  Does the option exist, if so, filter by it
                    $iSelectedIndex = !empty((int) $_GET['ddF'][$iFilterIndex]) ? (int) $_GET['ddF'][$iFilterIndex] : 0;
                    $oOption        = $oFilter->getOption($iSelectedIndex);

                    if ($oOption && $oOption->getValue()) {
                        $sWhereFilter = $this->getCountCommonCompileFiltersString(
                            $oFilter->getColumn(),
                            $oOption->getValue(),
                            $oOption->isQuery()
                        );
                    }

                } else {

                    //  No filtering happening but does this item have an item checked by default?
                    foreach ($oFilter->getOptions() as $oOption) {
                        if ($oOption->isSelected()) {
                            $sWhereFilter = $this->getCountCommonCompileFiltersString(
                                $oFilter->getColumn(),
                                $oOption->getValue(),
                                $oOption->isQuery()
                            );
                            break;
                        }
                    }
                }

                if (!empty($sWhereFilter)) {

                    if (!isset($aData['where'])) {
                        $aData['where'] = [];
                    }

                    $aData['where'][] = $sWhereFilter;
                }
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Compile the filter string
     *
     * @param  string  $sColumn  The column
     * @param  mixed   $mValue   The value
     * @param  boolean $bIsQuery Whether the value is an SQL wuery or not
     *
     * @return string
     */
    protected function getCountCommonCompileFiltersString($sColumn, $mValue, $bIsQuery)
    {
        if (is_object($mValue) && ($mValue instanceof \Closure)) {
            $mValue = $mValue();
        }

        $oDb = Factory::service('Database');
        if ($bIsQuery) {
            $aBits = [$mValue];
        } elseif (!is_array($mValue)) {
            $aBits = [
                $oDb->escape_str($sColumn, false),
                '=',
                $oDb->escape($mValue),
            ];
        } else {
            $sOperator = getFromArray(0, $mValue);
            $sValue    = getFromArray(1, $mValue);
            $bEscape   = (bool) getFromArray(2, $mValue, true);
            $aBits     = [
                $oDb->escape_str($sColumn, false),
                $sOperator,
                $bEscape ? $oDb->escape($sValue) : $sValue,
            ];
        }

        return trim(implode(' ', $aBits));
    }

    // --------------------------------------------------------------------------

    /**
     * Compiles any where's into the query
     *
     * @param  array &$aData The data array
     *
     * @return void
     */
    protected function getCountCommonCompileWheres(array &$aData)
    {
        $oDb = Factory::service('Database');

        /**
         * Handle where's
         *
         * This is an array of the various type of where that can be passed in via $aData.
         * The first element is the key and the second is how multiple items within the
         * group should be glued together.
         *
         * Each type of where is grouped in it's own set of parenthesis, multiple groups
         * are glued together with ANDs
         */

        $aWheres = [
            'where'           => 'AND',
            'or_where'        => 'OR',
            'where_in'        => 'AND',
            'or_where_in'     => 'OR',
            'where_not_in'    => 'AND',
            'or_where_not_in' => 'OR',
        ];

        $aWhereCompiled = [];

        foreach ($aWheres as $sWhereType => $sWhereGlue) {

            if (!empty($aData[$sWhereType])) {

                $aWhereCompiled[$sWhereType] = [];

                if (is_array($aData[$sWhereType])) {

                    /**
                     * The value is an array. For each element we need to compile as appropriate
                     * and add to $aWhereCompiled.
                     */

                    foreach ($aData[$sWhereType] as $mWhere) {

                        if (is_string($mWhere)) {

                            /**
                             * The value is a straight up string, assume this is a compiled
                             * where string,
                             */

                            $aWhereCompiled[$sWhereType][] = $mWhere;

                        } else {

                            /**
                             * The value is an array, try and determine the various parts
                             * of the query. We use strings which are unlikely to be found
                             * as falsey values (such as null) are perfectly likely.
                             */

                            //  Work out column
                            $mColumn = isset($mWhere['column']) ? $mWhere['column'] : '[NAILS-COL-NOT-FOUND]';

                            if ($mColumn === '[NAILS-COL-NOT-FOUND]') {
                                $mColumn = isset($mWhere[0]) && is_string($mWhere[0]) ? $mWhere[0] : null;
                            }

                            //  Work out value
                            $mVal = isset($mWhere['value']) ? $mWhere['value'] : '[NAILS-VAL-NOT-FOUND]';

                            if ($mVal === '[NAILS-VAL-NOT-FOUND]') {
                                $mVal = isset($mWhere[1]) ? $mWhere[1] : null;
                            }

                            //  Escaped?
                            $bEscape = isset($mWhere['escape']) ? (bool) $mWhere['escape'] : '[NAILS-ESCAPE-NOT-FOUND]';

                            if ($bEscape === '[NAILS-ESCAPE-NOT-FOUND]') {
                                $bEscape = isset($mWhere[2]) ? $mWhere[2] : true;
                            }

                            //  If the $mColumn is an array then we should concat them together
                            if (is_array($mColumn)) {
                                $mColumn = 'CONCAT_WS(" ", ' . implode(',', $mColumn) . ')';
                            }

                            //  Test if there's an SQL operator
                            if (!(bool) preg_match('/(<|>|!|=|\sIS NULL|\sIS NOT NULL|\sEXISTS|\sBETWEEN|\sLIKE|\sIN\s*\(|\s)/i', trim($mColumn))) {
                                $sOperator = is_null($mVal) ? ' IS ' : '=';
                            } else {
                                $sOperator = '';
                            }

                            //  Got something?
                            if ($mColumn) {

                                switch ($sWhereType) {

                                    case 'where' :
                                    case 'or_where' :

                                        if ($bEscape) {
                                            $mVal = $oDb->escape($mVal);
                                        }

                                        $aWhereCompiled[$sWhereType][] = $mColumn . $sOperator . $mVal;
                                        break;

                                    case 'where_in' :
                                    case 'or_where_in' :

                                        if (!is_array($mVal)) {
                                            $mVal = (array) $mVal;
                                        }

                                        if (!empty($mVal)) {
                                            if ($bEscape) {
                                                foreach ($mVal as &$sValue) {
                                                    $sValue = $oDb->escape($sValue);
                                                }
                                            }

                                            $aWhereCompiled[$sWhereType][] = $mColumn . ' IN (' . implode(',', $mVal) . ')';
                                        }
                                        break;

                                    case 'where_not_in' :
                                    case 'or_where_not_in' :

                                        if (!is_array($mVal)) {
                                            $mVal = (array) $mVal;
                                        }

                                        if (!empty($mVal)) {
                                            if ($bEscape) {
                                                foreach ($mVal as &$sValue) {
                                                    $sValue = $oDb->escape($sValue);
                                                }
                                            }

                                            $aWhereCompiled[$sWhereType][] = $mColumn . ' NOT IN (' . implode(',', $mVal) . ')';
                                        }
                                        break;
                                }
                            }
                        }
                    }

                } elseif (is_string($aData[$sWhereType])) {

                    /**
                     * The value is a straight up string, assume this is a compiled
                     * where string,
                     */

                    $aWhereCompiled[$sWhereType][] = $aData[$sWhereType];
                }
            }
        }

        /**
         * Now we need to compile all the conditionals into one big super query.
         * $aWhereStr is an array of the compressed where strings... will make
         * sense shortly...
         */

        if (!empty($aWhereCompiled)) {

            $aWhereStr = [];

            foreach ($aWhereCompiled as $sWhereType => $sValue) {
                if (!empty($sValue)) {
                    $aWhereStr[] = '(' . implode(' ' . $aWheres[$sWhereType] . ' ', $sValue) . ')';
                }
            }

            //  And reduce $aWhereStr to an actual string, like the name suggests
            if (!empty($aWhereStr)) {
                $aWhereStr = implode(' AND ', $aWhereStr);
                $oDb->where($aWhereStr);
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Compiles any like's into the query
     *
     * @param  array &$aData The data array
     *
     * @return void
     */
    protected function getCountCommonCompileLikes(array &$aData)
    {
        $oDb = Factory::service('Database');

        $aLikes = [
            'like'        => 'AND',
            'or_like'     => 'OR',
            'not_like'    => 'AND',
            'or_not_like' => 'OR',
        ];

        $aLikeCompiled = [];

        foreach ($aLikes as $sLikeType => $sLikeGlue) {

            if (!empty($aData[$sLikeType])) {

                $aLikeCompiled[$sLikeType] = [];

                if (is_array($aData[$sLikeType])) {

                    /**
                     * The value is an array. For each element we need to compile as appropriate
                     * and add to $aLikeCompiled.
                     */

                    foreach ($aData[$sLikeType] as $mWhere) {

                        if (is_string($mWhere)) {

                            /**
                             * The value is a straight up string, assume this is a compiled
                             * where string,
                             */

                            $aLikeCompiled[$sLikeType][] = $mWhere;

                        } else {

                            /**
                             * The value is an array, try and determine the various parts
                             * of the query. We use strings which are unlikely to be found
                             * as falsey values (such as null) are perfectly likely.
                             */

                            //  Work out column
                            $mColumn = isset($mWhere['column']) ? $mWhere['column'] : '[NAILS-COL-NOT-FOUND]';

                            if ($mColumn === '[NAILS-COL-NOT-FOUND]') {
                                $mColumn = isset($mWhere[0]) && is_string($mWhere[0]) ? $mWhere[0] : null;
                            }

                            //  Work out value
                            $mVal = isset($mWhere['value']) ? $mWhere['value'] : '[NAILS-VAL-NOT-FOUND]';

                            if ($mVal === '[NAILS-VAL-NOT-FOUND]') {
                                $mVal = isset($mWhere[1]) ? $mWhere[1] : null;
                            }

                            //  Escaped?
                            $bEscape = isset($mWhere['escape']) ? (bool) $mWhere['escape'] : '[NAILS-ESCAPE-NOT-FOUND]';

                            if ($bEscape === '[NAILS-ESCAPE-NOT-FOUND]') {
                                $bEscape = isset($mWhere[2]) ? $mWhere[2] : true;
                            }

                            if ($bEscape) {
                                $mVal = $oDb->escape_like_str($mVal);
                            }

                            //  If the $mColumn is an array then we should concat them together
                            if (is_array($mColumn)) {
                                $mColumn = 'CONCAT_WS(" ", ' . implode(',', $mColumn) . ')';
                            }

                            //  Got something?
                            if ($mColumn) {
                                switch ($sLikeType) {

                                    case 'like' :
                                    case 'or_like' :
                                        $aLikeCompiled[$sLikeType][] = $mColumn . ' LIKE "%' . $mVal . '%"';
                                        break;

                                    case 'not_like' :
                                    case 'or_not_like' :
                                        $aLikeCompiled[$sLikeType][] = $mColumn . ' NOT LIKE "%' . $mVal . '%"';
                                        break;
                                }
                            }
                        }
                    }

                } elseif (is_string($aData[$sLikeType])) {

                    /**
                     * The value is a straight up string, assume this is a compiled
                     * where string,
                     */

                    $aLikeCompiled[$sLikeType][] = $aData[$sLikeType];
                }
            }
        }

        /**
         * Now we need to compile all the conditionals into one big super query.
         * $aWhereStr is an array of the compressed where strings... will make
         * sense shortly...
         */

        if (!empty($aLikeCompiled)) {

            $aWhereStr = [];

            foreach ($aLikeCompiled as $sLikeType => $sValue) {
                $aWhereStr[] = '(' . implode(' ' . $aLikes[$sLikeType] . ' ', $sValue) . ')';
            }

            //  And reduce $aWhereStr to an actual string, like the name suggests
            $aWhereStr = implode(' AND ', $aWhereStr);
            $oDb->where($aWhereStr);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Compiles the sort element into the query
     *
     * @param  array &$aData The data array
     *
     * @return void
     */
    protected function getCountCommonCompileSort(array &$aData)
    {
        $oDb = Factory::service('Database');

        if (!empty($aData['sort'])) {

            /**
             * How we handle sorting
             * =====================
             *
             * - If $aData['sort'] is a string assume it's the field to sort on, use the default order
             * - If $aData['sort'] is a single dimension array then assume the first element (or the element
             *   named 'column') is the column; and the second element (or the element named 'order') is the
             *   direction to sort in
             * - If $aData['sort'] is a multidimensional array then loop each element and test as above.
             *
             **/

            if (is_string($aData['sort'])) {

                //  String
                $oDb->order_by($aData['sort']);

            } elseif (is_array($aData['sort'])) {

                $mFirst = reset($aData['sort']);

                if (is_string($mFirst)) {

                    //  Single dimension array
                    $aSort = $this->getCountCommonParseSort($aData['sort']);

                    if (!empty($aSort['column'])) {
                        $oDb->order_by($aSort['column'], $aSort['order']);
                    }

                } else {

                    //  Multi dimension array
                    foreach ($aData['sort'] as $sort) {

                        $aSort = $this->getCountCommonParseSort($sort);

                        if (!empty($aSort['column'])) {
                            $oDb->order_by($aSort['column'], $aSort['order']);
                        }
                    }
                }
            }

        }
    }

    // --------------------------------------------------------------------------

    /**
     * Parse the sort variable
     *
     * @param  string|array $mSort The sort variable
     *
     * @return array
     */
    protected function getCountCommonParseSort($mSort)
    {
        $aOut = ['column' => null, 'order' => null];

        // --------------------------------------------------------------------------

        if (is_string($mSort)) {

            $aOut['column'] = $mSort;
            return $aOut;

        } elseif (isset($mSort['column'])) {

            $aOut['column'] = $mSort['column'];

        } else {

            //  Take the first element
            $aOut['column'] = reset($mSort);
            $aOut['column'] = is_string($aOut['column']) ? $aOut['column'] : null;
        }

        if ($aOut['column']) {

            //  Determine order
            if (isset($mSort['order'])) {

                $aOut['order'] = $mSort['order'];

            } elseif (count($mSort) > 1) {

                //  Take the last element
                $aOut['order'] = end($mSort);
                $aOut['order'] = is_string($aOut['order']) ? $aOut['order'] : null;
            }
        }

        return $aOut;
    }
}
