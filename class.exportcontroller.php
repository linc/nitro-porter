<?php
/**
 * @copyright Vanilla Forums Inc. 2010-2015
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * Generic controller implemented by forum-specific ones.
 */
abstract class ExportController {

    /** @var array Database connection info */
    protected $DbInfo = array();

    /** @var array Required tables, columns set per exporter */
    protected $SourceTables = array();

    /** @var ExportModel */
    protected $Ex = null;

    /** Forum-specific export routine */
    abstract protected function forumExport($Ex);

    /**
     * Construct and set the controller's properties from the posted form.
     */
    public function __construct() {
        global $Supported;

        $this->handleInfoForm();

        $this->Ex = new ExportModel;
        $this->Ex->Controller = $this;
        $this->Ex->setConnection(
            $this->DbInfo['dbhost'],
            $this->DbInfo['dbuser'],
            $this->DbInfo['dbpass'],
            $this->DbInfo['dbname']
        );

        // That's not sexy but it gets the job done :D
        $lcClassName = strtolower(get_class($this));
        $hasDefaultPrefix = !empty($Supported[$lcClassName]['prefix']);

        if (isset($this->DbInfo['prefix'])) {
            $this->Ex->Prefix = $this->DbInfo['prefix'];
        } elseif ($hasDefaultPrefix) {
            $this->Ex->Prefix = $Supported[$lcClassName]['prefix'];
        }
        $this->Ex->Destination = $this->param('dest', 'file');
        $this->Ex->DestDb = $this->param('destdb', null);
        $this->Ex->TestMode = $this->param('test', false);

        /**
         * Selective exports
         * 1. Get the comma-separated list of tables and turn it into an array
         * 2. Trim off the whitespace
         * 3. Normalize case to lower
         * 4. Save to the ExportModel instance
         */
        $RestrictedTables = $this->param('tables', false);
        if (!empty($RestrictedTables)) {
            $RestrictedTables = explode(',', $RestrictedTables);

            if (is_array($RestrictedTables) && !empty($RestrictedTables)) {
                $RestrictedTables = array_map('trim', $RestrictedTables);
                $RestrictedTables = array_map('strtolower', $RestrictedTables);

                $this->Ex->RestrictedTables = $RestrictedTables;
            }
        }
    }

    /**
     * Set CDN file prefix if one is given.
     *
     * @return string
     */
    public function cdnPrefix() {
        $Cdn = rtrim($this->param('cdn', ''), '/');
        if ($Cdn) {
            $Cdn .= '/';
        }

        return $Cdn;
    }

    /**
     * Logic for export process.
     */
    public function doExport() {
        global $Supported;

        // Test connection
        $Msg = $this->testDatabase();
        if ($Msg === true) {

            // Test src tables' existence structure
            $Msg = $this->Ex->verifySource($this->SourceTables);
            if ($Msg === true) {
                // Good src tables - Start dump
                $this->Ex->useCompression(true);
                $this->Ex->FilenamePrefix = $this->DbInfo['dbname'];
                set_time_limit(60 * 60);

//            ob_start();
                $this->forumExport($this->Ex);
//            $Errors = ob_get_clean();

                $Msg = $this->Ex->Comments;

                // Write the results.  Send no path if we don't know where it went.
                $RelativePath = ($this->param('destpath', false)) ? false : $this->Ex->Path;
                viewExportResult($Msg, 'Info', $RelativePath);
            } else {
                viewForm(array('Supported' => $Supported, 'Msg' => $Msg, 'Info' => $this->DbInfo));
            } // Back to form with error
        } else {
            viewForm(array('Supported' => $Supported, 'Msg' => $Msg, 'Info' => $this->DbInfo));
        } // Back to form with error
    }

    /**
     * User submitted db connection info.
     */
    public function handleInfoForm() {
        $this->DbInfo = array(
            'dbhost' => $_POST['dbhost'],
            'dbuser' => $_POST['dbuser'],
            'dbpass' => $_POST['dbpass'],
            'dbname' => $_POST['dbname'],
            'type' => $_POST['type'],
            'prefix' => isset($_POST['prefix']) ? preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['prefix']) : null,
        );
    }

    /**
     * Retrieve a parameter passed to the export process.
     *
     * @param string $Name
     * @param mixed $Default Fallback value.
     * @return mixed Value of the parameter.
     */
    public function param($Name, $Default = false) {
        if (isset($_POST[$Name])) {
            return $_POST[$Name];
        } elseif (isset($_GET[$Name])) {
            return $_GET[$Name];
        } else {
            return $Default;
        }
    }

    /**
     * Test database connection info.
     *
     * @return string|bool True on success, message on failure.
     */
    public function testDatabase() {
        // Connection
        if (!function_exists('mysql_connect')) {
            $Result = 'mysql_connect is an undefined function.  Verify MySQL extension is installed and enabled.';
        } elseif ($C = @mysql_connect($this->DbInfo['dbhost'], $this->DbInfo['dbuser'], $this->DbInfo['dbpass'])) {
            // Database
            if (mysql_select_db($this->DbInfo['dbname'], $C)) {
                mysql_close($C);
                $Result = true;
            } else {
                mysql_close($C);
                $Result = "Could not find database '{$this->DbInfo['dbname']}'.";
            }
        } else {
            $Result = 'Could not connect to ' . $this->DbInfo['dbhost'] . ' as ' . $this->DbInfo['dbuser'] . ' with given password.';
        }

        return $Result;
    }
}

?>
