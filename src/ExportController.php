<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace Porter;

use Porter\Database\DbFactory;

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
    public function build()
    {
        // Set the database source prefix.
        $supported = PackageSupport::getInstance()->get();
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

        // Set model properties.
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
     * @return array
     */
    public function getDbInfo(): array
    {
        return $this->dbInfo;
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
    public function run(\Porter\Request $request)
    {
        // Test connection.
        $this->testDatabase();

        // Test src tables' existence & structure.
        $this->ex->verifySource($this->sourceTables);

        // Good src tables - Start dump
        $this->ex->useCompression(true);
        set_time_limit(0);

        $this->forumExport($this->ex);

        // Write the results.  Send no path if we don't know where it went.
        $relativePath = ($this->param('destpath', false)) ? false : $this->ex->path;
        Render::viewExportResult($this->ex->comments, 'Info', $relativePath);
    }

    /**
     * User submitted db connection info.
     */
    public function handleInfoForm()
    {
        $this->dbInfo['package'] = $_POST['package'];
        $this->dbInfo['prefix'] = !isset($_POST['emptyprefix']) ?
            preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['src-prefix']) : null;
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
        $value = Request::instance()->get($name);
        if ($value === '') {
            $value = $default;
        }
        return $value;
    }

    /**
     * Test database connection info.
     */
    public function testDatabase()
    {
        $dbFactory = new DbFactory($this->dbInfo, 'pdo');
        $dbFactory->getInstance();
    }

    /**
     * @param ExportModel
     * @return void
     */
    public function setModel(ExportModel $model): void
    {
        $this->ex = $model;
    }
}
