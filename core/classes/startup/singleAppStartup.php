<?php
/**
 * Created by PhpStorm.
 * User: sevidmusic
 * Date: 4/23/17
 * Time: 11:21 PM
 */

namespace DarlingCms\classes\startup;

/**
 * Class singleAppStartup. Starts up a single \DarlingCms\classes\component\app component.
 *
 * @todo: Consider adding a getOutput() method to the contract of a startup object
 *        so startup objects have the option of capturing and managing output on startup,
 *        restart, or shutdown. This new method should be added to Astartup or Istartup.
 *
 * @package DarlingCms\classes\startup
 */
class singleAppStartup extends \DarlingCms\abstractions\startup\Astartup
{
    private $app;
    private $appDirectoryPath;
    private $appFileName;
    private $appFileExtension;
    private $appFilePath;

    /**
     * Initialize the $app, $appDirectoryPath, $appFileName, $appFileExtension, and $appFilePath properties.
     * Note: The __construct() method is called upon instantiation and whenever setApp() is called.
     */
    public function __construct(\DarlingCms\classes\component\app $app)
    {
        $this->app = $app;
        $this->appDirectoryPath = str_replace('core/classes/startup', '', __DIR__) . 'apps/' . $app->getComponentName() . '/';
        $this->appFileName = $app->getComponentName();
        $this->appFileExtension = 'php';
        $this->appFilePath = "$this->appDirectoryPath$this->appFileName.$this->appFileExtension";
    }


    /**
     * Returns the internal \DarlingCms\classes\component\app object instance.
     * @return \DarlingCms\classes\component\app The current \DarlingCms\classes\component\app app instance.
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * Set the internal \DarlingCms\classes\component\app object instance.
     * @param \DarlingCms\classes\component\app $app Instance of a \DarlingCms\classes\component\app object.
     * @return bool True if app was set, false otherwise.
     */
    public function setApp(\DarlingCms\classes\component\app $app)
    {
        $this->__construct($app);
        return isset($app);
    }

    /**
     * Captures the app's output, if the app is enabled. The captured app
     * output is stored as a component attribute in the custom attributes array
     * under the index 'appOutput'.
     *
     * Hint: To get the app output, use the internal \DarlingCms\classes\component\app object
     * instance's getComponentAttributeValue() method.
     *
     * i.e., $this->getApp()->getComponentAttributeValue('customAttributes')['appOutput'];
     *
     * @return bool True if app output was captured, false otherwise.
     */
    protected function run()
    {
        /* Only run if app is enabled.  */
        if ($this->app->getComponentAttributeValue('enabled') === true) {
            $status = $this->captureAppOutput();
            return $status;
        }
        return false;
    }

    /**
     * Uses an output buffer to capture output from a specified app and stores it
     * in the appOutput array indexed under the name of the specified app.
     *
     * WARNING: Be aware of the consequences of using output buffers when working with this method, some
     *          of which are noted below. If anything un-expected happens when working with this method,
     *          it may be directly related to the use of an output buffer in this methods logic.
     *
     * Note: This method handles capturing of app output differently based on whether or not
     *       app has an access controller. Since this method uses output buffers to capture app
     *       output, and PHP complains about to many headers being sent if session_start() is called
     *       from both inside and outside the output buffer, all interactions with sessions must be done
     *       from within the output buffer so session interaction can occur within this method, and from
     *       within the app this class is starting up. Basically, access controllers require session
     *       interaction, app output is captured within an output buffer, therefore, the session started by
     *       this method must be started from within this methods output buffer so the session interaction
     *       can happen here, and from within the app.
     *
     * WARNING: When developing or refactoring this method, be aware that any output must happen within output buffer.
     *          i.e, calls to var_dump(), echo, or the like, must happen within output buffer or PHP will complain
     *          about headers already being sent when starting up an app that has an access controller, and interacts
     *          with sessions from within the app itself.
     *          i.e., If app has an access controller, and the app itself makes any calls to session_start() from
     *          within the app, and output is generated by this method outside the output buffer using var_dump,
     *          echo, or the like, PHP will complain and session interaction will not be possible from within
     *          the app.
     *
     * Dev Note: When developing or debugging this method be aware no output will be captured if an access controller
     *           denies access. i.e., Calls to var_dump() and the like that occur within the output buffer will not
     *           output to the page if an access controller denies access. This is also true if the attempt to
     *           include an app fails. This is because this method closes the output buffer without calling
     *           ob_get_contents() if the app denied access or failed to be included.
     *
     * @param string $enabledApp Name of the app to capture output from.
     *
     * @return bool True if output was captured in the appOutput array successfully, false otherwise.
     */
    final private function captureAppOutput()
    {
        /* Clear app output from any previous start-ups. */
        $this->stop();
        /* Start output buffer. */
        ob_start();
        switch ($this->app->hasAccessController()) {
            /* App has an access controller. */
            case true:
                /* DEV NOTE: : Though it's not the best practice, the session and crud objects are instantiated
                 *             here because they are only used here. If the need for session or crud interaction
                 *             becomes required outside this method's scope then this class should be refactored
                 *             so the session and crud objects are injected via the constructor and saved to new
                 *             private properties $session and $crud respectively.
                 */
                /* Instantiate a new session object to use for any session interaction. */
                $session = new \DarlingCms\classes\crud\session();
                /* Instantiate a new crud object to use for interaction with stored data. */
                $crud = new \DarlingCms\classes\crud\registeredJsonCrud();
                /* Read the current user's user object from storage. | Current user determined by value of the 'currentUser' session variable. */
                $user = $crud->read($session->read('currentUser'), 'DarlingCms\classes\accessControl\user');
                /* Check that the current user was found, that the user is logged in, and that user has access by validating against the app's access controller. */
                switch (($user !== false) && $user->isLoggedIn() === true && ($this->app->getAccessController()->validateAccess($user))) {
                    /* User has access, set $accessGranted var to true. */
                    case true:
                        $accessGranted = true;
                        break;
                    /* User does not have access, set $accessGranted var to false. */
                    default:
                        $accessGranted = false;
                        break;
                }
                break;
            /* App does not have an access controller, grant access to everyone. */
            default:
                $accessGranted = true;
                break;

        }
        /* If access was not granted, or app was not included successfully, return false. */
        if ($accessGranted === false || $this->includeApp() === false) {
            /* End output buffer */
            ob_end_clean();
            /* Access was denied, or include failed, return false. */
            return false;
        }
        /* Capture app output from the output buffer. */
        $this->app->setCustomAttribute('appOutput', trim(ob_get_contents()));
        /* End output buffer */
        ob_end_clean();
        /* Return true if app output was captured, false otherwise. */
        return (is_null($this->app->getComponentAttributeValue('customAttributes')['appOutput']) === false);
    }

    /**
     * Clears the app output by setting it to null.
     *
     * @return bool True if app output was cleared, false otherwise.
     */
    protected function stop()
    {
        return $this->app->setCustomAttribute('appOutput', null);
    }

    /**
     * Attempts to include the specified app.
     *
     * @param string $enabledApp Name of the app to include.
     * @return mixed Returns the int 1 if include succeeded, false otherwise.
     */
    final private function includeApp()
    {
        return include($this->appFilePath);
    }
}
