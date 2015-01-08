<?php

interface CORE_NAILS_ErrorHandler_Interface
{
    //  Object methods
    public static function init();
    public static function error($errno, $errstr, $errfile, $errline);
    public static function exception($exception);
    public static function fatal();
}

class CORE_NAILS_ErrorHandler
{
    public function __construct()
    {
        /**
         * Work out how we're handling errors. Production environments take into
         * consideration error reporting. Non-production environments use local
         * error reporting, that is CI Error reporting
         */

        if (ENVIRONMENT === 'PRODUCTION')
        {
            switch (strtoupper(DEPLOY_ERROR_REPORTING_HANDLER)) {

                /**
                 * Rollbar
                 * Always enabled on PRODUCTION, selectively enabled elsewhere (fallsback to Nails ErrorHandler)
                 */

                case 'ROLLBAR':

                    if (ENVIRONMENT === 'PRODUCTION') {

                        $className = 'Rollbar';

                    } else {

                        if (defined('DEPLOY_ROLLBAR_DEV_ENABLED')) {

                            if (DEPLOY_ROLLBAR_DEV_ENABLED) {

                                $className = 'Rollbar';
                            }
                        }
                    }
                    break;
            }

            if (!empty($className)) {

                require_once NAILS_COMMON_PATH . 'core/CORE_NAILS_ErrorHandler_' . $className . '.php';

                //  Init the chosen handler
                $errorHandler = 'CORE_NAILS_ErrorHandler_' . $className;
                $errorHandler::init();

                //  Set the handlers
                set_error_handler($errorHandler . '::error');
                set_exception_handler($errorHandler . '::exception');
                register_shutdown_function($errorHandler . '::fatal');

            } else {

                //  Basic Nails ErrorHandler
                set_error_handler('CORE_NAILS_ErrorHandler::error');
                set_exception_handler('CORE_NAILS_ErrorHandler::exception');
                register_shutdown_function('CORE_NAILS_ErrorHandler::fatal');
            }

        } else {

            //  Basic Nails ErrorHandler
            set_error_handler('CORE_NAILS_ErrorHandler::error');
            set_exception_handler('CORE_NAILS_ErrorHandler::exception');
            register_shutdown_function('CORE_NAILS_ErrorHandler::fatal');
        }
    }

    // --------------------------------------------------------------------------

    public static function error($errno, $errstr, $errfile, $errline)
    {
        //  Let this bubble to the normal Codeigniter error handler
        return _exception_handler($errno, $errstr, $errfile, $errline);
    }

    // --------------------------------------------------------------------------

    public static function exception($exception)
    {
        $details       = new stdClass();
        $details->code = $exception->getCode();
        $details->msg  = $exception->getMessage();
        $details->file = $exception->getFile();
        $details->line = $exception->getLine();

        $errMsg  = 'Uncaught Exception with message "' . $details->msg . '" and code "';
        $errMsg .= $details->code . '" in ' . $details->file . ' on line ' . $details->line;

        //  Show we log the item?
        if (config_item('log_threshold') != 0)
        {

            log_message('error', $errMsg, true);
        }

        $subject = 'Uncaught Exception';
        $message = $errMsg;

        if (ENVIRONMENT == 'PRODUCTION') {

            self::sendDeveloperMail($subject, $message);
        }


        self::showFatalErrorScreen($subject, $message, $details);
    }

    // --------------------------------------------------------------------------

    public static function fatal()
    {
        $error = error_get_last();

        if (!is_null($error) && $error['type'] === E_ERROR) {

            $details       = new stdClass();
            $details->code = $error['type'];
            $details->msg  = $error['message'];
            $details->file = $error['file'];
            $details->line = $error['line'];

            $subject = 'Fatal Error';
            $message = $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'];

            //  Send a note to the dev if on production
            if (ENVIRONMENT == 'PRODUCTION') {

                self::sendDeveloperMail($subject, $message);
            }

            self::showFatalErrorScreen($subject, $message, $details);
        }
    }

    // --------------------------------------------------------------------------

    public static function showFatalErrorScreen($subject = '', $message = '', $details = null)
    {
        if (is_null($details)) {

            $details            = new stdClass();
            $details->code      = '';
            $details->msg       = '';
            $details->file      = '';
            $details->line      = '';
        }

        //  Get the backtrace
        if (function_exists('debug_backtrace')) {

            $details->backtrace = debug_backtrace();

        } else {

            $details->backtrace = array();
        }

        //  Flush the output buffer
        ob_clean();

        //  Non-production and have an app-specific dev error file?
        if (ENVIRONMENT != 'PRODUCTION' && is_file(FCPATH . APPPATH . 'errors/error_fatal_dev.php')) {

            include_once FCPATH . APPPATH . 'errors/error_fatal_dev.php';

        //  Production and have an app-specific error file?
        } elseif (ENVIRONMENT == 'PRODUCTION' && is_file(FCPATH . APPPATH . 'errors/error_fatal.php')) {

            include_once FCPATH . APPPATH . 'errors/error_fatal.php';

        //  Non-production?
        } elseif (ENVIRONMENT != 'PRODUCTION') {

            include_once NAILS_COMMON_PATH . 'errors/error_fatal_dev.php';

        //  Production
        } else {

            include_once NAILS_COMMON_PATH . 'errors/error_fatal.php';
        }

        exit(0);
    }

    // --------------------------------------------------------------------------

    public static function sendDeveloperMail($subject, $message)
    {
        if (!APP_DEVELOPER_EMAIL) {

            //  Log the fact there's no email
            log_message('error', 'Attempting to send developer email, but APP_DEVELOPER_EMAIL is not defined.');
            return FALSE;
        }

        // --------------------------------------------------------------------------

        $fromEmail = 'root@' . gethostname();

        if (function_exists('app_setting')) {

            $fromName = app_setting('from_name', 'email');

            if (empty($fromName)) {

                $fromName = 'Log Error Reporter';
            }

            $replyTo = app_setting('from_email', 'email');

            if (empty($replyTo)) {

                $replyTo = $fromEmail;
            }

        } else {

            $fromName = 'Fatal Error Reporter';
            $replyTo  = $fromEmail;
        }

        // --------------------------------------------------------------------------

        $_ci =& get_instance();

        $info = array(
            'uri'     => isset($_ci->uri)         ? $_ci->uri->uri_string()            : '',
            'session' => isset($_ci->session)     ? serialize($_ci->session->userdata) : '',
            'post'    => isset($_POST)            ? serialize($_POST)                  : '',
            'get'     => isset($_GET)             ? serialize($_GET)                   : '',
            'server'  => isset($_SERVER)          ? serialize($_SERVER)                : '',
            'globals' => isset($GLOBALS['error']) ? serialize($GLOBALS['error'])       : ''
        );

        //  Closures cannot be serialized
        try
        {
            $info['debug_backtrace'] = serialize(debug_backtrace());

        } catch(Exception $e) {

            $info['debug_backtrace'] = 'Failed to serialize get Backtrace: ' .  $e->getMessage();
        }

        $extended   = 'URI: ' . $info['uri'] . "\n\n";
        $extended  .= 'SESSION: ' . $info['session'] . "\n\n";
        $extended  .= 'POST: ' . $info['post'] . "\n\n";
        $extended  .= 'GET: ' . $info['get'] . "\n\n";
        $extended  .= 'SERVER: ' . $info['server'] . "\n\n";
        $extended  .= 'GLOBALS: ' . $info['globals'] . "\n\n";
        $extended  .= 'BACKTRACE: ' . $info['debug_backtrace'] . "\n\n";

        if (isset($_ci->db)) {

            $extended  .= 'LAST KNOWN QUERY: ' .   $_ci->db->last_query() . "\n\n";
        }

        // --------------------------------------------------------------------------

        //  Prepare and send
        $mimeBoundary = md5(uniqid(time()));
        $to = strtoupper(ENVIRONMENT) != 'PRODUCTION' && EMAIL_OVERRIDE ? EMAIL_OVERRIDE : APP_DEVELOPER_EMAIL;

        //  Headers
        $headers  = 'From: ' . $fromName . ' <' . $fromEmail . '>' . "\r\n";
        $headers .= 'Reply-To: ' . $replyTo . "\r\n";
        $headers .= 'X-Mailer: PHP/' . phpversion()  . "\r\n";
        $headers .= 'X-Priority: 1 (Highest)' . "\r\n";
        $headers .= 'X-Mailer: X-MSMail-Priority: High/' . "\r\n";
        $headers .= 'Importance: High' . "\r\n";
        $headers .= 'MIME-Version:1.0' . "\r\n";
        $headers .= 'Content-Type:multipart/mixed; boundary="' . $mimeBoundary . '"' . "\r\n\r\n";

        //  Message
        $headers .= '--' . $mimeBoundary . "\r\n";
        $headers .= 'Content-Type:text/html; charset="ISO-8859-1"' . "\r\n";
        $headers .= 'Content-Transfer-Encoding:7bit' . "\r\n\r\n";

        $headers .= '<html><head><style type="text/css">body { font:10pt Arial; }</style></head><body>';
        $headers .= str_replace("\r", '', str_replace("\n", '<br />', $message));
        $headers .= '</body></html>' . "\r\n\r\n";

        //  Attachment
        $headers .= '--' . $mimeBoundary . "\r\n";
        $headers .= 'Content-Type:application/octet-stream; name="debugging-data.txt"' . "\r\n";
        $headers .= 'Content-Transfer-Encoding:base64' . "\r\n";
        $headers .= 'Content-Disposition:attachment; filename="debugging-data.txt"' . "\r\n";
        $headers .= base64_encode($extended) . "\r\n\r\n";

        // --------------------------------------------------------------------------

        //  Send!
        if (!empty($to)) {

            if (function_exists('mail')) {

                @mail($to, '!! ' . $subject . ' - ' . APP_NAME , '', $headers);
                return true;

            } else {

                return false;
            }

        } else {

            return false;
        }
    }
}