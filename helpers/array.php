<?php

/**
 * This file provides array related helper functions
 *
 * @package     Nails
 * @subpackage  common
 * @category    Helper
 * @author      Nails Dev Team
 * @link
 */

if (!function_exists('arrayUniqueMulti')) {

    /**
     * Removes duplicate items from a multi-dimensional array
     * Hat-tip: http://phpdevblog.niknovo.com/2009/01/using-array-unique-with-multidimensional-arrays.html
     *
     * @param  array $aArray The array to filter
     *
     * @return array
     */
    function arrayUniqueMulti(array $aArray)
    {
        // Unique Array for return
        $aArrayRewrite = [];

        // Array with the md5 hashes
        $aArrayHashes = [];

        foreach ($aArray as $key => $item) {

            // Serialize the current element and create a md5 hash
            $hash = md5(serialize($item));

            /**
             * If the md5 didn't come up yet, add the element to to arrayRewrite,
             * otherwise drop it
             */

            if (!isset($aArrayHashes[$hash])) {

                // Save the current element hash
                $aArrayHashes[$hash] = $hash;

                // Add element to the unique Array
                $aArrayRewrite[$key] = $item;
            }
        }

        unset($aArrayHashes);
        unset($key);
        unset($item);
        unset($hash);

        return $aArrayRewrite;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('array_unique_multi')) {

    /**
     * Alias of arrayUniqueMulti()
     * @deprecated
     * @see arrayUniqueMulti
     */
    function array_unique_multi(array &$aArray)
    {
        trigger_error('Function ' . __METHOD__ . ' is deprecated', E_USER_DEPRECATED);
        return arrayUniqueMulti($aArray);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('arraySortMulti')) {

    /**
     * Sorts a multi dimensional array
     *
     * @param  array  &$aArray The array to sort
     * @param  string $sField  The key to sort on
     *
     * @return void
     */
    function arraySortMulti(array &$aArray, $sField)
    {
        uasort($aArray, function ($a, $b) use ($sField) {

            $oA = (object) $a;
            $oB = (object) $b;

            $mA = property_exists($oA, $sField) ? strtolower($oA->$sField) : null;
            $mB = property_exists($oB, $sField) ? strtolower($oB->$sField) : null;

            //  Equal?
            if ($mA == $mB) {
                return 0;
            }

            //  If $mA is a prefix of $mB then $mA comes first
            if (preg_match('/^' . preg_quote($mA, '/') . '/', $mB)) {
                return -1;
            }

            //  Not equal, work out which takes precedence
            $aSort = [$mA, $mB];
            sort($aSort);

            return $aSort[0] == $mA ? -1 : 1;
        });
    }
}

// --------------------------------------------------------------------------

if (!function_exists('array_sort_multi')) {

    /**
     * Alias of arraySortMulti()
     * @deprecated
     * @see arraySortMulti
     */
    function array_sort_multi(array &$aArray, $sField)
    {
        trigger_error('Function ' . __METHOD__ . ' is deprecated', E_USER_DEPRECATED);
        return arraySortMulti($aArray, $sField);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('arraySearchMulti')) {

    /**
     * Searches a multi-dimensional array
     *
     * @param  string $sValue Search value
     * @param  string $sKey   Key to search
     * @param  array  $aArray The array to search
     *
     * @return mixed         The array key on success, false on failure
     */
    function arraySearchMulti($sValue, $sKey, array $aArray)
    {
        foreach ($aArray as $k => $val) {

            if (is_array($val)) {

                if ($val[$sKey] == $sValue) {
                    return $k;
                }

            } elseif (is_object($val)) {
                if ($val->$sKey == $sValue) {
                    return $k;
                }
            }
        }
        return false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('array_search_multi')) {

    /**
     * Alias of arraySearchMulti()
     * @deprecated
     * @see arraySearchMulti
     */
    function array_search_multi($sValue, $sKey, array $aArray)
    {
        trigger_error('Function ' . __METHOD__ . ' is deprecated', E_USER_DEPRECATED);
        return arraySearchMulti($sValue, $sKey, $aArray);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('inArrayMulti')) {

    /**
     * Reports whether a value exists in a multi dimensional array
     *
     * @param  string $sValue The value to search for
     * @param  string $sKey   The key to search on
     * @param  array  $aArray The array to search
     *
     * @return boolean
     */
    function inArrayMulti($sValue, $sKey, array $aArray)
    {
        return arraySearchMulti($sValue, $sKey, $aArray) !== false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('in_array_multi')) {

    /**
     * Alias of inArrayMulti()
     * @deprecated
     * @see inArrayMulti
     */
    function in_array_multi($sValue, $sKey, array $aArray)
    {
        trigger_error('Function ' . __METHOD__ . ' is deprecated', E_USER_DEPRECATED);
        return inArrayMulti($sValue, $sKey, $aArray);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('arrayExtractProperty')) {

    /**
     * Extracts the value of properties from a multi-dimensional array into an array of those values
     *
     * @param array  $aInput    The array to iterate over
     * @param string $sProperty The property to extract
     *
     * @return array
     */
    function arrayExtractProperty(array $aInput, $sProperty)
    {
        $aOutput = [];
        foreach ($aInput as $mItem) {
            $aItem = (array) $mItem;
            if (array_key_exists($sProperty, $aItem)) {
                $aOutput[] = $aItem[$sProperty];
            }
        }
        return $aOutput;
    }
}

// --------------------------------------------------------------------------

//  Include the CodeIgniter original
include NAILS_CI_SYSTEM_PATH . 'helpers/array_helper.php';
