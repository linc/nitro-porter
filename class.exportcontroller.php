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

    /** @var bool Whether to stream result; deprecated. */
    protected $UseStreaming = false;

    /** @var ExportModel */
    protected $Ex = null;

    /** Forum-specific export routine */
    abstract protected function ForumExport($Ex);

    /**
     * Construct and set the controller's properties from the posted form.
     */
    public function __construct() {
        $this->HandleInfoForm();

        $this->Ex = new ExportModel;
        $this->Ex->Controller = $this;
        $this->Ex->SetConnection($this->DbInfo['dbhost'], $this->DbInfo['dbuser'], $this->DbInfo['dbpass'],
            $this->DbInfo['dbname']);
        $this->Ex->Prefix = $this->DbInfo['prefix'];
        $this->Ex->Destination = $this->Param('dest', 'file');
        $this->Ex->DestDb = $this->Param('destdb', null);
        $this->Ex->TestMode = $this->Param('test', false);
    }

    /**
     * Set CDN file prefix if one is given.
     *
     * @return string
     */
    public function CdnPrefix() {
        $Cdn = rtrim($this->Param('cdn', ''), '/');
        if ($Cdn) {
            $Cdn .= '/';
        }

        return $Cdn;
    }

    /**
     * Logic for export process.
     */
    public function DoExport() {
        global $Supported;

        // Test connection
        $Msg = $this->TestDatabase();
        if ($Msg === true) {

            // Test src tables' existence structure
            $Msg = $this->Ex->VerifySource($this->SourceTables);
            if ($Msg === true) {
                // Good src tables - Start dump
                $this->Ex->UseCompression(true);
                $this->Ex->FilenamePrefix = $this->DbInfo['dbname'];
                set_time_limit(60 * 60);

//            ob_start();
                $this->ForumExport($this->Ex);
//            $Errors = ob_get_clean();

                $Msg = $this->Ex->Comments;

                // Send no path if we don't know where it went.
                $RelativePath = ($this->Param('destpath', false)) ? false : $this->Ex->Path;
                ViewExportResult($Msg, 'Info', $RelativePath);
            } else {
                ViewForm(array('Supported' => $Supported, 'Msg' => $Msg, 'Info' => $this->DbInfo));
            } // Back to form with error
        } else {
            ViewForm(array('Supported' => $Supported, 'Msg' => $Msg, 'Info' => $this->DbInfo));
        } // Back to form with error
    }

    /**
     * User submitted db connection info.
     */
    public function HandleInfoForm() {
        $this->DbInfo = array(
            'dbhost' => $_POST['dbhost'],
            'dbuser' => $_POST['dbuser'],
            'dbpass' => $_POST['dbpass'],
            'dbname' => $_POST['dbname'],
            'type' => $_POST['type'],
            'prefix' => preg_replace('/[^A-Za-z0-9_-]/', '', $_POST['prefix'])
        );
    }

    /**
     * Retrieve a parameter passed to the export process.
     *
     * @param string $Name
     * @param mixed $Default Fallback value.
     * @return mixed Value of the parameter.
     */
    public function Param($Name, $Default = false) {
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
    public function TestDatabase() {
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
