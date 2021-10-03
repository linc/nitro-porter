<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace NitroPorter;

/**
 * Generic controller implemented by forum-specific ones.
 */
abstract class ExportController
{
    /**
     * @var array Database connection info
     */
    protected $dbInfo = array();

    /**
     * @var array Required tables, columns set per exporter
     */
    protected $sourceTables = array();

    /**
     * @var ExportModel
     */
    protected $ex = null;

    /**
     * Forum-specific export routine
     */
    abstract protected function forumExport($ex);

    /**
     * Register supported features.
     */
    public static function registerSupport()
    {
        $name = get_called_class();
        $slug = str_replace('NitroPorter\Package\\', '', $name);
        SupportManager::getInstance()->registerSupport($slug, $name::SUPPORTED);
    }

    /**
     * Construct and set the controller's properties from the posted form.
     */
    public function __construct()
    {
        $this->registerSupport();
        $supported = SupportManager::getInstance()->getSupport();

        $this->handleInfoForm();
        $dbfactory = new DbFactory($this->dbInfo, DB_EXTENSION);
        $this->ex = new ExportModel($dbfactory);
        $this->ex->controller = $this;
        $this->ex->prefix = '';

        // That's not sexy but it gets the job done :D
        $lcClassName = strtolower(get_class($this));
        $hasDefaultPrefix = !empty($supported[$lcClassName]['prefix']);

        if (isset($this->dbInfo['prefix'])) {
            if ($this->dbInfo['prefix'] === 'PACKAGE_DEFAULT') {
                if ($hasDefaultPrefix) {
                    $this->ex->prefix = $supported[$lcClassName]['prefix'];
                }
            } else {
                $this->ex->prefix = $this->dbInfo['prefix'];
            }
        }

        $this->ex->destination = $this->param('dest', 'file');
        $this->ex->destDb = $this->param('destdb', null);
        $this->ex->testMode = $this->param('test', false);

        /**
         * Selective exports
         * 1. Get the comma-separated list of tables and turn it into an array
         * 2. Trim off the whitespace
         * 3. Normalize case to lower
         * 4. Save to the ExportModel instance
         */
        $restrictedTables = $this->param('tables', false);
        if (!empty($restrictedTables)) {
            $restrictedTables = explode(',', $restrictedTables);

            if (is_array($restrictedTables) && !empty($restrictedTables)) {
                $restrictedTables = array_map('trim', $restrictedTables);
                $restrictedTables = array_map('strtolower', $restrictedTables);

                $this->ex->restrictedTables = $restrictedTables;
            }
        }
    }

    /**
     * Set CDN file prefix if one is given.
     *
     * @return string
     */
    public function cdnPrefix()
    {
        $cdn = rtrim($this->param('cdn', ''), '/');
        if ($cdn) {
            $cdn .= '/';
        }

        return $cdn;
    }

    /**
     * Logic for export process.
     */
    public function doExport()
    {
        $supported = \NitroPorter\SupportManager::getInstance()->getSupport();

        // Test connection
        $msg = $this->testDatabase();
        if ($msg === true) {
            // Test src tables' existence structure
            $msg = $this->ex->verifySource($this->sourceTables);
            if ($msg === true) {
                // Good src tables - Start dump
                $this->ex->useCompression(true);
                $this->ex->filenamePrefix = $this->dbInfo['dbname'];
                increaseMaxExecutionTime(3600);
                //            ob_start();
                $this->forumExport($this->ex);
                //            $Errors = ob_get_clean();

                $msg = $this->ex->comments;

                // Write the results.  Send no path if we don't know where it went.
                $relativePath = ($this->param('destpath', false)) ? false : $this->ex->path;
                viewExportResult($msg, 'Info', $relativePath);
            } else {
                viewForm(array('Supported' => $supported, 'Msg' => $msg, 'Info' => $this->dbInfo));
            } // Back to form with error
        } else {
            viewForm(array('Supported' => $supported, 'Msg' => $msg, 'Info' => $this->dbInfo));
        } // Back to form with error
    }

    /**
     * User submitted db connection info.
     */
    public function handleInfoForm()
    {
        $this->dbInfo = array(
            'dbhost' => $_POST['dbhost'],
            'dbuser' => $_POST['dbuser'],
            'dbpass' => $_POST['dbpass'],
            'dbname' => $_POST['dbname'],
            'type' => $_POST['type'],
            'prefix' => !isset($_POST['emptyprefix']) ? preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['prefix']) : null,
        );
    }

    /**
     * Retrieve a parameter passed to the export process.
     *
     * @param  string $name
     * @param  mixed  $default Fallback value.
     * @return mixed Value of the parameter.
     */
    public function param($name, $default = false)
    {
        if (isset($_POST[$name])) {
            return $_POST[$name];
        } elseif (isset($_GET[$name])) {
            return $_GET[$name];
        } else {
            return $default;
        }
    }

    /**
     * Test database connection info.
     *
     * @return string|bool True on success, message on failure.
     */
    public function testDatabase()
    {
        $dbFactory = new DbFactory($this->dbInfo, DB_EXTENSION);
        // Will die on error
        $dbFactory->getInstance();

        return true;
    }
}
