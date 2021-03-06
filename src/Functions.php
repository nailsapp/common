<?php

/**
 * Nails helper functions
 *
 * @package     Nails
 * @subpackage  common
 * @category    core
 * @author      Nails Dev Team
 */

namespace Nails;

use Nails\Common\Exception\NailsException;
use Nails\Common\Service\ErrorHandler;
use Nails\Common\Service\Input;
use Nails\Config;

/**
 * Class Functions
 *
 * @package Nails
 */
class Functions
{
    /**
     * Define a constant if it is not already defined
     *
     * @param string $sConstantName The constant to define
     * @param mixed  $mValue        The constant's value
     */
    public static function define(string $sConstantName, $mValue): void
    {
        if (!defined($sConstantName)) {
            define($sConstantName, $mValue);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a cryptographically secure key
     *
     * @param string|null $sSalt A salt to use
     *
     * @return string
     * @throws \Exception
     */
    public static function generateKey(string $sSalt = null): string
    {
        return base64_encode($sSalt . random_bytes(32));
    }

    // --------------------------------------------------------------------------

    /**
     * Detects whether the current page is secure or not
     *
     * @return bool
     */
    public static function isPageSecure(): bool
    {
        if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') {

            //  Page is being served through HTTPS
            return true;

        } elseif (isset($_SERVER['SERVER_NAME']) && isset($_SERVER['REQUEST_URI']) && Config::get('SECURE_BASE_URL') != Config::get('BASE_URL')) {

            //  Not being served through HTTPS, but does the URL of the page begin
            //  with SECURE_BASE_URL (when BASE_URL is different)

            $sUrl = 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
            return (bool) preg_match('#^' . Config::get('SECURE_BASE_URL') . '.*#', $sUrl);
        }

        //  Unknown, assume not
        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches the relative path between two directories
     * Hat tip: Thanks to Gordon for this one; http://stackoverflow.com/a/2638272/789224
     *
     * @param string $sFrom Path 1
     * @param string $sTo   Path 2
     *
     * @return string
     */
    public static function getRelativePath(string $sFrom, string $sTo): string
    {
        $aFrom    = explode('/', $sFrom);
        $aTo      = explode('/', $sTo);
        $aRelPath = $aTo;

        foreach ($aFrom as $iDepth => $sDir) {

            //  Find first non-matching dir
            if ($sDir === $aTo[$iDepth]) {
                //  Ignore this directory
                array_shift($aRelPath);
            } else {

                //  Get number of remaining dirs to $aFrom
                $remaining = count($aFrom) - $iDepth;

                if ($remaining > 1) {

                    // add traversals up to first matching dir
                    $padLength = (count($aRelPath) + $remaining - 1) * -1;
                    $aRelPath  = array_pad($aRelPath, $padLength, '..');
                    break;

                } else {
                    $aRelPath[0] = './' . $aRelPath[0];
                }
            }
        }

        return implode('/', $aRelPath);
    }

    // --------------------------------------------------------------------------

    /**
     * Throw an error
     *
     * @param string $sMessage      The error message
     * @param string $sSubject      The error subject
     * @param int    $iStatusCode   The status code
     * @param bool   $bUseException Whether to use an exception
     *
     * @throws \Nails\Common\Exception\FactoryException
     * @throws \Nails\Common\Exception\NailsException
     * @throws \Nails\Common\Exception\ViewNotFoundException
     * @throws \ReflectionException
     */
    public static function showError(
        string $sMessage = '',
        string $sSubject = '',
        int $iStatusCode = 500,
        bool $bUseException = true
    ): void {

        if (is_array($sMessage)) {
            $sMessage = implode('<br>', $sMessage);
        }

        if ($bUseException) {
            throw new NailsException($sMessage, $iStatusCode);
        } else {
            /** @var ErrorHandler $oErrorHandler */
            $oErrorHandler = Factory::service('ErrorHandler');
            $oErrorHandler->showFatalErrorScreen($sSubject, $sMessage);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Renders the 401 page, optionally logging the error to the database.
     * If a user is not logged in they are directed to the login page.
     *
     * @param string $sFlashMessage The flash message to display to the user
     * @param string $sReturnUrl    The URL to return to after logging in
     * @param bool   $bLogError     Whether to log the error or not
     */
    public static function show401(
        string $sFlashMessage = null,
        string $sReturnUrl = null,
        bool $bLogError = true
    ): void {

        /** @var ErrorHandler $oErrorHandler */
        $oErrorHandler = Factory::service('ErrorHandler');
        $oErrorHandler->show401($sFlashMessage, $sReturnUrl, $bLogError);
    }

    // --------------------------------------------------------------------------

    /**
     * Renders the 404 page, logging disabled by default.
     *
     * @param bool $bLogError Whether to log the error or not
     */
    public static function show404(bool $bLogError = false): void
    {
        /** @var ErrorHandler $oErrorHandler */
        $oErrorHandler = Factory::service('ErrorHandler');
        $oErrorHandler->show404($bLogError);
    }

    // --------------------------------------------------------------------------

    /**
     * Whether the current request is being executed on the CLI
     *
     * @return bool
     */
    public static function isCli(): bool
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        return $oInput::isCli();
    }

    // --------------------------------------------------------------------------

    /**
     * Whether the current request is an Ajax request
     *
     * @return bool
     */
    public static function isAjax(): bool
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        return $oInput::isAjax();
    }
}
