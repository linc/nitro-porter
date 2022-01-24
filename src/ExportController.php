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
     * @deprecated
     * @var array Database connection info
     */
    public $dbInfo = array();

    /**
     * @deprecated
     * @var array Required tables, columns set per exporter
     */
    protected $sourceTables = array();

    /**
     * @deprecated
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
     * @return array
     */
    public function getDbInfo(): array
    {
        return $this->dbInfo;
    }

    /**
     * Set CDN file prefix if one is given.
     *
     * @deprecated
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

        // Start export.
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
