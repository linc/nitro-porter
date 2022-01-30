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
    protected $exportModel = null;

    /**
     * Forum-specific export routine
     */
    abstract protected function forumExport(ExportModel $ex);

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
        $this->exportModel->verifySource($this->sourceTables);

        // Start export.
        set_time_limit(0);
        $this->forumExport($this->exportModel);

        // Write the results.  Send no path if we don't know where it went.
        $relativePath = ($this->param('destpath', false)) ? false : $this->exportModel->path;
        Render::viewExportResult($this->exportModel->comments, 'Info', $relativePath);
    }

    /**
     * User submitted db connection info.
     * @deprecated
     */
    public function handleInfoForm()
    {
        $this->dbInfo['package'] = $_POST['package'];
        $this->dbInfo['prefix'] = !isset($_POST['emptyprefix']) ?
            preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['src-prefix']) : null;
    }

    /**
     * Set primary database connection data.
     * @deprecated
     */
    public function loadPrimaryDatabase($info)
    {
        $this->dbInfo['dbhost'] = $info['host'];
        $this->dbInfo['dbuser'] = $info['user'];
        $this->dbInfo['dbpass'] = $info['pass'];
        $this->dbInfo['dbname'] = $info['name'];
    }

    /**
     * Retrieve a parameter passed to the export process.
     * @deprecated
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
     * @deprecated
     * Test database connection info.
     */
    public function testDatabase()
    {
        $dbFactory = new DbFactory($this->dbInfo, 'pdo');
        $dbFactory->getInstance();
    }

    /**
     * @deprecated
     * @param ExportModel
     * @return void
     */
    public function setModel(ExportModel $model): void
    {
        $this->exportModel = $model;
    }
}
