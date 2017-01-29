<?php

/**
 * This class is the main execution point for all page requests. It
 * checks and configures the Nails environment.
 *
 * @package     Nails
 * @subpackage  common
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Common\Controller;

use Nails\Factory;
use Nails\Environment;
use Nails\Common\Exception\NailsException;

class Base extends \MX_Controller
{
    protected $data;
    protected $user;
    protected $nailsErrorHandler;
    protected $log;

    // --------------------------------------------------------------------------

    /**
     * Build the main framework. All autoloaded items have been loaded and
     * instantiated by this point and are safe to use.
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Set up services
        Factory::setup();

        //  Setup Events
        $oEventService = Factory::service('Event');

        //  Call the SYSTEM.STARTUP event, the earliest event the app can bind to.
        $oEventService->trigger('SYSTEM.STARTUP', 'nailsapp/common');

        // --------------------------------------------------------------------------

        //  Is Nails in maintenance mode?
        $this->maintenanceMode();

        // --------------------------------------------------------------------------

        //  Nails PHP Version Check
        $this->checkPhpVersion();

        // --------------------------------------------------------------------------

        //  Configure error reporting
        $this->setErrorReporting();

        // --------------------------------------------------------------------------

        //  Set the default content-type
        $this->output->set_content_type('text/html; charset=utf-8');

        // --------------------------------------------------------------------------

        //  Define data array (used extensively in views)
        $this->data =& getControllerData();

        // --------------------------------------------------------------------------

        //  Define all the packages
        $this->autoloadItems();

        // --------------------------------------------------------------------------

        /**
         * If we're on a staging environment then prompt for a password; but only if
         * a password has been defined in app.php
         */

        $this->passwordProtected();

        // --------------------------------------------------------------------------

        //  Test that the cache is writable
        $this->testCache();

        // --------------------------------------------------------------------------

        //  Instanciate the user model
        $this->instantiateUser();

        // --------------------------------------------------------------------------

        //  Instanciate languages
        $this->instantiateLanguages();

        // --------------------------------------------------------------------------

        /**
         * Is the user suspended?
         * Executed here so that both the user and language systems are initialised
         * (so that any errors can be shown in the correct language).
         */

        $this->isUserSuspended();

        // --------------------------------------------------------------------------

        //  Instanciate DateTime
        $this->instantiateDateTime();

        // --------------------------------------------------------------------------

        //  Need to generate the routes_app.php file?
        if (defined('NAILS_STARTUP_GENERATE_APP_ROUTES') && NAILS_STARTUP_GENERATE_APP_ROUTES) {

            $oRoutesModel = Factory::model('Routes');

            if (!$oRoutesModel->update()) {

                //  Fall over, routes_app.php *must* be there
                $subject  = 'Failed To generate routes_app.php';
                $message  = 'routes_app.php was not found and could not be generated. ';
                $message .= $oRoutesModel->lastError();

                showFatalError($subject, $message);

            } else {

                //  Routes exist now, instruct the browser to try again
                if ($this->input->post()) {

                    redirect($this->input->server('REQUEST_URI'), 'Location', 307);

                } else {

                    redirect($this->input->server('REQUEST_URI'));
                }
            }
        }

        // --------------------------------------------------------------------------

        //  Set User Feedback alerts for the views
        $this->data['error']    = $this->userFeedback->get('error')    ?: $this->session->flashdata('error');
        $this->data['negative'] = $this->userFeedback->get('negative') ?: $this->session->flashdata('negative');
        $this->data['success']  = $this->userFeedback->get('success')  ?: $this->session->flashdata('success');
        $this->data['positive'] = $this->userFeedback->get('positive') ?: $this->session->flashdata('positive');
        $this->data['info']     = $this->userFeedback->get('info')     ?: $this->session->flashdata('info');
        $this->data['warning']  = $this->userFeedback->get('message')  ?: $this->session->flashdata('warning');

        //  @deprecated
        $this->data['message']  = $this->userFeedback->get('message')  ?: $this->session->flashdata('message');
        $this->data['notice']   = $this->userFeedback->get('notice')   ?: $this->session->flashdata('notice');

        // --------------------------------------------------------------------------

        //  Other defaults
        $this->data['page']                   = new \stdClass();
        $this->data['page']->title            = '';
        $this->data['page']->seo              = new \stdClass();
        $this->data['page']->seo->title       = '';
        $this->data['page']->seo->description = '';
        $this->data['page']->seo->keywords    = '';

        // --------------------------------------------------------------------------

        /**
         * Forced maintenance mode?
         */
        if (appSetting('maintenance_mode_enabled', 'site')) {
            $this->maintenanceMode(true);
        }

        // --------------------------------------------------------------------------

        /**
         * Set some meta tags which should be used on every site.
         */

        $this->meta->addRaw(array(
            'charset' => 'utf-8'
        ));

        $this->meta->addRaw(array(
            'name'    => 'viewport',
            'content' => 'width=device-width, initial-scale=1'
        ));

        // --------------------------------------------------------------------------

        /**
         * Finally, set any custom CSS and JS as defined in admin
         * @todo bring this in via a hook or something
         */

        $sCustomJs  = appSetting('site_custom_js', 'site');
        $sCustomCss = appSetting('site_custom_css', 'site');

        if (!empty($sCustomJs)) {
            $this->asset->inline($sCustomJs, 'JS');
        }

        if (!empty($sCustomCss)) {
            $this->asset->inline($sCustomCss, 'CSS');
        }

        // --------------------------------------------------------------------------

        //  Call the SYSTEM.READY event, the system is all geared up and ready to go
        $oEventService->trigger('SYSTEM.READY', 'nailsapp/common');
    }

    // --------------------------------------------------------------------------

    /**
     * Checks that the version of PHP is sufficient to run all enabled modules
     * @return void
     */
    protected function checkPhpVersion()
    {
        /**
         * PHP Version Check
         * =================
         *
         * We need to loop through all available modules and have a look at what version
         * of PHP they require, we'll then take the highest version and set that as our
         * minimum supported value.
         *
         * To set a requirement, within the module's nails object in composer.json,
         * specify the minPhpVersion value. You should also specify the appropriate
         * constraint for composer in the "require" section of composer.json.
         *
         * e.g:
         *
         *  "extra":
         *  {
         *      "nails" :
         *      {
         *          "minPhpVersion": "5.6.0"
         *      }
         *  }
         */

        define('NAILS_MIN_PHP_VERSION', _NAILS_MIN_PHP_VERSION());

        if (version_compare(PHP_VERSION, NAILS_MIN_PHP_VERSION, '<')) {

            $subject  = 'PHP Version ' . PHP_VERSION . ' is not supported by Nails';
            $message  = 'The version of PHP you are running is not supported. Nails requires at least ';
            $message .= 'PHP version ' . NAILS_MIN_PHP_VERSION;

            if (function_exists('_NAILS_ERROR')) {

                _NAILS_ERROR($message, $subject);

            } else {

                echo '<h1>ERROR: ' . $subject . '</h1>';
                echo '<h2>' . $message . '</h2>';
                exit(0);
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the appropriate error reporting values and handlers
     * @return void
     */
    protected function setErrorReporting()
    {
        /**
         * Configure how verbose PHP is; Everything except E_STRICT and E_ERROR;
         * we'll let the errorHandler pickup fatal errors
         */

        error_reporting(E_ALL ^ E_STRICT ^ E_ERROR);

        //  Configure whether errors are shown or no
        if (function_exists('ini_set')) {

            switch (Environment::get()) {

                case 'PRODUCTION' :
                    //  Suppress all errors on production
                    ini_set('display_errors', false);
                    break;

                default :
                    //  Show errors everywhere else
                    ini_set('display_errors', true);
                    break;
            }
        }

        $this->nailsErrorHandler = Factory::service('ErrorHandler');
    }

    // --------------------------------------------------------------------------

    /**
     * Tests that the cache is writable
     * @return void
     */
    protected function testCache()
    {
        if (is_writable(DEPLOY_CACHE_DIR)) {

            return true;

        } elseif (is_dir(DEPLOY_CACHE_DIR)) {

            //  Attempt to chmod the dir
            if (@chmod(DEPLOY_CACHE_DIR, FILE_WRITE_MODE)) {

                return true;

            } else {

                throw new NailsException(
                    'The app\'s cache dir "' . DEPLOY_CACHE_DIR . '" exists but is not writable.',
                    1
                );
            }

        } elseif (@mkdir(DEPLOY_CACHE_DIR)) {

            return true;

        } else {

            throw new NailsException(
                'The app\'s cache dir "' . DEPLOY_CACHE_DIR . '" does not exist and could not be created.',
                1
            );
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Checks if Maintenance Mode is enabled, shows the holding page if so.
     * @param  boolean $force  Force maintenance mode on
     * @param  string  $sTitle Override the page title
     * @param  string  $sBody  Override the page body
     * @return void
     */
    protected function maintenanceMode($force = false, $sTitle = '', $sBody = '')
    {
        if ($force || file_exists(FCPATH . '.MAINTENANCE')) {

            /**
             * We're in maintenance mode. This can happen very early so we need to
             * make sure that we've loaded everything we need to load as we're
             * exiting whether we like it or not
             */

            try {

                //  Get the database so that the appSetting() functions will work
                $oDb = Factory::service('Database');

                //  Set the package path (so helpers and libraries are loaded correctly)
                $this->load->add_package_path(NAILS_COMMON_PATH);

                //  Load the helpers
                Factory::service('encrypt');
                Factory::helper('app_setting');
                Factory::helper('tools');

                $whitelistIp   = (array) appSetting('maintenance_mode_whitelist', 'site');
                $isWhiteListed = isIpInRange($this->input->ipAddress(), $whitelistIp);

                //  Customisations
                $sMaintenanceTitle = $sTitle ? $sTitle : appSetting('maintenance_mode_title', 'site');
                $sMaintenanceBody  = $sBody ? $sBody : appSetting('maintenance_mode_body', 'site');

            } catch (\Exception $e) {

                //  No database, or it failed, defaults
                $isWhiteListed     = false;
                $sMaintenanceTitle = $sTitle;
                $sMaintenanceBody  = $sBody;
            }

            // --------------------------------------------------------------------------

            if (!$isWhiteListed) {

                if (!$this->input->is_cli_request()) {

                    header($this->input->server('SERVER_PROTOCOL') . ' 503 Service Temporarily Unavailable');
                    header('Status: 503 Service Temporarily Unavailable');
                    header('Retry-After: 7200');

                    // --------------------------------------------------------------------------

                    //  If the request is an AJAX request, or the URL is on the API then spit back JSON
                    if ($this->input->is_ajax_request() || $this->uri->segment(1) == 'api') {

                        header('Cache-Control: no-store, no-cache, must-revalidate');
                        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                        header('Content-type: application/json');
                        header('Pragma: no-cache');

                        $_out = array('status' => 503, 'error' => 'Down for Maintenance');

                        echo json_encode($_out);

                    //  Otherwise, render some HTML
                    } else {

                        //  Look for an app override
                        if (file_exists(FCPATH . APPPATH . 'views/maintenance/maintenance.php')) {

                            require FCPATH . APPPATH . 'views/maintenance/maintenance.php';

                        //  Fall back to the Nails maintenance page
                        } elseif (file_exists(NAILS_COMMON_PATH . 'views/maintenance/maintenance.php')) {

                            require NAILS_COMMON_PATH . 'views/maintenance/maintenance.php';

                        //  Fall back, back to plain text
                        } else {

                            echo '<h1>Down for maintenance</h1>';
                        }
                    }

                } else {

                    echo 'Down for Maintenance' . "\n";
                }
                exit(0);
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Checks if credentials should be requested for staging environments
     * @return void
     */
    protected function passwordProtected()
    {
        /**
         * To password protect an environment you must create a constant which is
         * a JSON string of key/value pairs (where the key is the username and the
         * value is a sha1 hash of the password):
         *
         *     {
         *         'john': '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8',
         *         'amy': '3fc9b689459d738f8c88a3a48aa9e33542016b7a4052e001aaa536fca74813cb'
         *     }
         *
         * You may also whitelist IP/IP Ranges by providing an array of IP Ranges
         *
         *      [
         *          '123.456.789.123',
         *          '123.456/789'
         *      ]
         */

        $sConstantName          = 'APP_USER_PASS_' . Environment::get();
        $sConstantNameWhitelist = 'APP_USER_PASS_WHITELIST_' . Environment::get();

        if (!isCli() && defined($sConstantName)) {

            //  On the whitelist?
            if (defined($sConstantNameWhitelist)) {

                $aWhitelsitedIps = @json_decode(constant($sConstantNameWhitelist));
                $bWhitelisted    = isIpInRange($this->input->ipAddress(), $aWhitelsitedIps);

            } else {

                $bWhitelisted = false;
            }

            if (!$bWhitelisted) {

                $oCredentials = @json_decode(constant($sConstantName));

                if (empty($_SERVER['PHP_AUTH_USER'])) {
                    $this->passwordProtectedRequest();
                }

                if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {

                    //  Determine the users
                    $isSet   = isset($oCredentials->{$_SERVER['PHP_AUTH_USER']});
                    $isEqual = $oCredentials->{$_SERVER['PHP_AUTH_USER']} == hash('sha256', $_SERVER['PHP_AUTH_PW']);

                    if (!$isSet || !$isEqual) {
                        $this->passwordProtectedRequest();
                    }

                } else {

                    $this->passwordProtectedRequest();
                }
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Requests staging credentials
     * @return void
     */
    protected function passwordProtectedRequest()
    {
        header('WWW-Authenticate: Basic realm="' . APP_NAME . ' - Restricted Area"');
        header($this->input->server('SERVER_PROTOCOL') . ' 401 Unauthorized');
        $message = 'You are not authorised to access this installation.';
        include NAILS_COMMON_PATH . 'errors/error_401.php';
        exit(0);
    }

    // --------------------------------------------------------------------------

    /**
     * Sets up date & time handling
     * @return void
     */
    protected function instantiateDateTime()
    {
        $oDateTimeModel = Factory::model('DateTime');

        //  Define default date format
        $oDefaultDateFormat = $oDateTimeModel->getDateFormatDefault();

        if (empty($oDefaultDateFormat)) {
            throw new NailsException(
                'No default date format has been set, or it\'s been set incorrectly.',
                1
            );
        }

        define('APP_DEFAULT_DATETIME_FORMAT_DATE_SLUG', $oDefaultDateFormat->slug);
        define('APP_DEFAULT_DATETIME_FORMAT_DATE_LABEL', $oDefaultDateFormat->label);
        define('APP_DEFAULT_DATETIME_FORMAT_DATE_FORMAT', $oDefaultDateFormat->format);

        //  Define default time format
        $oDefaultTimeFormat = $oDateTimeModel->getTimeFormatDefault();

        if (empty($oDefaultTimeFormat)) {
            throw new NailsException(
                'No default time format has been set, or it\'s been set incorrectly.',
                1
            );
        }

        define('APP_DEFAULT_DATETIME_FORMAT_TIME_SLUG', $oDefaultTimeFormat->slug);
        define('APP_DEFAULT_DATETIME_FORMAT_TIME_LABEL', $oDefaultTimeFormat->label);
        define('APP_DEFAULT_DATETIME_FORMAT_TIME_FORMAT', $oDefaultTimeFormat->format);

        // --------------------------------------------------------------------------

        /**
         * Set the timezones.
         * Choose the user's timezone - starting with their preference, followed by
         * the app's default.
         */

        if (activeUser('timezone')) {

            $sTimezoneUser = activeUser('timezone');

        } else {

            $sTimezoneUser = $oDateTimeModel->getTimezoneDefault();
        }

        $oDateTimeModel->setTimezones('UTC', $sTimezoneUser);

        // --------------------------------------------------------------------------

        //  Set the user date/time formats
        $sFormatDate = activeUser('datetime_format_date');
        $sFormatDate = $sFormatDate ? $sFormatDate : APP_DEFAULT_DATETIME_FORMAT_DATE_SLUG;

        $sFormatTime = activeUser('datetime_format_time');
        $sFormatTime = $sFormatTime ? $sFormatTime : APP_DEFAULT_DATETIME_FORMAT_TIME_SLUG;

        $oDateTimeModel->setFormats($sFormatDate, $sFormatTime);

        // --------------------------------------------------------------------------

        //  Make sure the system and the database are running on UTC
        date_default_timezone_set('UTC');
        $this->db->query('SET time_zone = \'+0:00\'');
    }

    // --------------------------------------------------------------------------

    /**
     * Sets up language handling
     * @return void
     */
    protected function instantiateLanguages()
    {
        //  Define default language
        $oLanguageModel = Factory::model('Language');
        $oDefault       = $oLanguageModel->getDefault();

        if (empty($oDefault)) {
            showFatalError('No default language has been set, or it\'s been set incorrectly.');
        }

        define('APP_DEFAULT_LANG_CODE', $oDefault->code);
        define('APP_DEFAULT_LANG_LABEL', $oDefault->label);

        // --------------------------------------------------------------------------

        /**
         * Set any global preferences for this user, e.g languages, fall back to the
         * app's default language (defined in config.php).
         */

        $sUserLangCode = activeUser('language');

        if (!empty($sUserLangCode)) {

            define('RENDER_LANG_CODE', $sUserLangCode);

        } else {

            define('RENDER_LANG_CODE', APP_DEFAULT_LANG_CODE);
        }

        //  Set the language config item which codeigniter will use.
        $oConfig = Factory::service('Config');
        $oConfig->set_item('language', RENDER_LANG_CODE);

        //  Load the Nails. generic lang file
        $this->lang->load('nails');
    }

    // --------------------------------------------------------------------------

    /**
     * Autoloads all items (helpers, models, libraries etc) that we'll need
     * @return void [description]
     */
    protected function autoloadItems()
    {
        /**
         * This is an important part. Here we are defining all the packages to load.
         * this translates as "where CodeIgniter will look for stuff".
         *
         * We have to do a few manual hacks to ensure things work as expected, i.e.
         * the load/check order is:
         *
         * 1. The Application
         * 2. Available modules
         * 3. Nails Common
         */

        //  Reset
        $oConfig = Factory::service('Config');
        $oConfig->_config_paths = array();

        $aPaths = array();

        //  Nails Common
        $aPaths[] = NAILS_COMMON_PATH;

        //  Available Modules
        $aAvailableModules = _NAILS_GET_MODULES();

        foreach ($aAvailableModules as $oModule) {
            $aPaths[] = $oModule->path;
        }

        //  The Application
        $aPaths[] = FCPATH . APPPATH;

        foreach ($aPaths as $sPath) {

            $this->load->add_package_path($sPath);
        }

        // --------------------------------------------------------------------------

        //  Load Helpers
        Factory::helper('app_setting');
        Factory::helper('app_notification');
        Factory::helper('date');
        Factory::helper('url');
        Factory::helper('cookie');
        Factory::helper('form');
        Factory::helper('html');
        Factory::helper('tools');
        Factory::helper('debug');
        Factory::helper('language');
        Factory::helper('text');
        Factory::helper('exception');
        Factory::helper('typography');
        Factory::helper('log');

        //  Autoload module helpers
        foreach ($aAvailableModules as $oModule) {
            if (!empty($oModule->autoload->helpers) && is_array($oModule->autoload->helpers)) {
                foreach ($oModule->autoload->helpers as $sHelper) {
                    Factory::helper($sHelper, $oModule->name);
                }
            }
        }

        // --------------------------------------------------------------------------

        /**
         * Common models & libraries
         * @note: We have to load this way so that the property is taken up by the CI
         * super object and therefore more reliably accessible (e.g in CMS module).
         * @todo  reduce this coupling
         * @todo  implement userFeedback library throughout
         */

        $oCi =& get_instance();

        //  Models
        //  Autoload module models
        foreach ($aAvailableModules as $oModule) {
            if (!empty($oModule->autoload->models) && is_array($oModule->autoload->models)) {
                foreach ($oModule->autoload->models as $oModel) {
                    foreach ($oModel as $sAssignTo => $sModelName) {
                        $oCi->{$sAssignTo} = Factory::model($sModelName, $oModule->name);
                    }
                }
            }
        }

        //  Libraries
        $oCi->db           = Factory::service('Database');
        $oCi->meta         = Factory::service('Meta');
        $oCi->asset        = Factory::service('Asset');
        $oCi->userFeedback = Factory::service('UserFeedback');
        $oCi->encrypt      = Factory::service('Encrypt');
        $oCi->logger       = Factory::service('Logger');

        //  Autoload module services
        foreach ($aAvailableModules as $oModule) {
            if (!empty($oModule->autoload->services) && is_array($oModule->autoload->services)) {
                foreach ($oModule->autoload->services as $oService) {
                    foreach ($oService as $sAssignTo => $sServiceName) {
                        $oCi->{$sAssignTo} = Factory::service($sServiceName, $oModule->name);
                    }
                }
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Set up the active user
     * @return void
     */
    protected function instantiateUser()
    {
        /**
         * Find a remembered user and initialise the user model; this routine checks
         * the user's cookies and set's up the session for an existing or new user.
         */

        $this->user_model->init();

        // --------------------------------------------------------------------------

        //  Inject the user object into the user_group &user_password
        //  @todo use the factory
        $this->user_group_model->setUserObject($this->user_model);
        $this->user_password_model->setUserObject($this->user_model);

        // --------------------------------------------------------------------------

        //  Shortcut/backwards compatibility
        $this->user = $this->user_model;

        //  Set a $user variable (for the views)
        $this->data['user'] = $this->user_model;
        $this->data['user_group'] = $this->user_group_model;
        $this->data['user_password'] = $this->user_password_model;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the active user is suspended and, if so, logs them out.
     * @return void
     */
    protected function isUserSuspended()
    {
        //  Check if this user is suspended
        if ($this->user_model->isLoggedIn() && activeUser('is_suspended')) {

            //  Load models and langs
            $oAuthModel = Factory::model('Auth', 'nailsapp/module-auth');
            $this->lang->load('auth/auth');

            //  Log the user out
            $oAuthModel->logout();

            //  Create a new session
            $this->session->sess_create();

            //  Give them feedback
            $this->session->set_flashdata('error', lang('auth_login_fail_suspended'));
            redirect('/');
        }
    }
}
