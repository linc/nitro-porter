<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace NitroPorter;

use NitroPorter\Database\DbFactory;

/**
 * Generic controller implemented by forum-specific ones.
 */
abstract class ExportController
{
    public const SUPPORTED = [
        'name' => '',
        'prefix' => '',
        'features' => [],
    ];

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
    public static function getSupport(): array
    {
        return static::SUPPORTED;
    }

    /**
     * Construct and set the controller's properties from the posted form.
     */
    public function __construct()
    {
        // Wire old database into model.
        $this->loadPrimaryDatabase();
        $this->handleInfoForm();
        $this->wireupModel();

        // That's not sexy but it gets the job done :D
        $supported = SupportManager::getInstance()->getSupport();
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
        // Test connection
        $msg = $this->testDatabase();
        if ($msg !== true) {
            // Back to form with error
            Render::viewForm(array('Msg' => $msg, 'Info' => $this->dbInfo));
            exit();
        }

        // Test src tables' existence structure
        $msg = $this->ex->verifySource($this->sourceTables);
        if ($msg !== true) {
            // Back to form with error
            Render::viewForm(array('Msg' => $msg, 'Info' => $this->dbInfo));
            exit();
        }

        // Good src tables - Start dump
        $this->ex->useCompression(true);
        $this->ex->filenamePrefix = $this->dbInfo['dbname'];
        $this->increaseMaxExecutionTime(3600);

        $this->forumExport($this->ex);
        $msg = $this->ex->comments;

        // Write the results.  Send no path if we don't know where it went.
        $relativePath = ($this->param('destpath', false)) ? false : $this->ex->path;
        Render::viewExportResult($msg, 'Info', $relativePath);
    }

    /**
     * Used to increase php max_execution_time value.
     *
     * @param  int $maxExecutionTime PHP max execution time in seconds.
     * @return bool Returns true if max_execution_time was increased (or stayed the same) or false otherwise.
     */
    protected function increaseMaxExecutionTime($maxExecutionTime)
    {
        $iniMaxExecutionTime = ini_get('max_execution_time');
        // max_execution_time == 0 means no limit.
        if ($iniMaxExecutionTime === '0') {
            return true;
        }
        if (((string)$maxExecutionTime) === '0') {
            return set_time_limit(0);
        }
        if (!ctype_digit($iniMaxExecutionTime) || $iniMaxExecutionTime < $maxExecutionTime) {
            return set_time_limit($maxExecutionTime);
        }
        return true;
    }

    /**
     * User submitted db connection info.
     */
    public function handleInfoForm()
    {
        $this->dbInfo['package'] = $_POST['package'];
        $this->dbInfo['prefix'] = !isset($_POST['emptyprefix']) ?
            preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['prefix']) : null;
    }

    /**
     * Set primary database connection data.
     */
    public function loadPrimaryDatabase()
    {
        $config = loadConfig();
        $primary_db = $config['connections']['databases'][0]; // @todo
        $this->dbInfo['dbhost'] = $primary_db['host'];
        $this->dbInfo['dbuser'] = $primary_db['user'];
        $this->dbInfo['dbpass'] = $primary_db['pass'];
        $this->dbInfo['dbname'] = $primary_db['name'];
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
        $dbFactory = new DbFactory($this->dbInfo, 'pdo');
        // Will die on error
        $dbFactory->getInstance();

        return true;
    }

    /**
     * @return void
     */
    public function wireupModel(): void
    {
        $dbfactory = new DbFactory($this->dbInfo, 'pdo');
        $this->ex = new ExportModel($dbfactory);
        $this->ex->controller = $this;
        $this->ex->prefix = '';
    }
}
