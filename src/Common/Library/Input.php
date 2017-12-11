<?php

/**
 * The class abstracts CodeIgniter's Input class.
 *
 * @package     Nails
 * @subpackage  common
 * @category    Library
 * @author      Nails Dev Team
 * @link
 * @todo        Remove dependency on CI
 */

namespace Nails\Common\Library;

class Input
{
    /**
     * The CodeIgniter Input object
     * @var \CI_Input
     */
    private $oInput;

    // --------------------------------------------------------------------------

    /**
     * Input constructor.
     */
    public function __construct()
    {
        $oCi          = get_instance();
        $this->oInput = $oCi->input;
    }

    // --------------------------------------------------------------------------

    /**
     * Route calls to the CodeIgniter Input class
     *
     * @param  string $sMethod    The method being called
     * @param  array  $aArguments Any arguments being passed
     *
     * @return mixed
     */
    public function __call($sMethod, $aArguments)
    {
        if (method_exists($this, $sMethod)) {
            return call_user_func_array([$this, $sMethod], $aArguments);
        } else {
            return call_user_func_array([$this->oInput, $sMethod], $aArguments);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Pass any property "gets" to the CodeIgniter Input class
     *
     * @param  string $sProperty The property to get
     *
     * @return mixed
     */
    public function __get($sProperty)
    {
        return $this->oInput->{$sProperty};
    }

    // --------------------------------------------------------------------------

    /**
     * Pass any property "sets" to the CodeIgniter Input class
     *
     * @param  string $sProperty The property to set
     * @param  mixed  $mValue    The value to set
     *
     * @return void
     */
    public function __set($sProperty, $mValue)
    {
        $this->oInput->{$sProperty} = $mValue;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the user's IP Address. Extended to allow this method to be called from a command line environment.
     * This override may not be needed in future implementations of CodeIgniter.
     * @return string
     */
    public function ipAddress()
    {
        if (isCli()) {
            $sHostname = gethostname();
            return gethostbyname($sHostname);
        } else {
            return $this->oInput->ip_address();
        }
    }
}
