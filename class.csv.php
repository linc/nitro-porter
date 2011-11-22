<?php
/**
 * Utility additions for CSV-based exporter tools.
 *
 * @copyright Vanilla Forums Inc. 2011
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * Adds CSV utility methods for CSV-based exporters.
 *
 * @package VanillaPorter
 */
abstract class CsvController extends ExportController {
   /**
    * Import a CSV table into a database.
    *
    * @param string $Path
    * @param string $TableName
    * @param array $ColumnInfo
    */
   public function ImportCsv($Path, $TableName, $ColumnInfo = array()) {
      $this->_DefineCsvTable($Path, $TableName, $ColumnInfo);

      $this->Ex->Query("truncate table `$TableName`;");

      $QPath = mysql_escape_string($Path);

      $Sql = "load data infile '$QPath' into table $TableName
         character set utf8
         columns terminated by ','
         optionally enclosed by '\"'
         lines terminated by ',\\n'
         ignore 1 lines";
      $this->Ex->Query($Sql);
   }

   /**
    * Build database tables based on information we can glean.
    *
    * @param string $Path
    * @param string $TableName
    * @param array $ColumnInfo
    */
   protected function _DefineCsvTable($Path, $TableName, $ColumnInfo = array()) {
      // Grab the header information.
      $fp = fopen($Path, 'rb');
      $HeaderLine = fgets($fp);
      fclose($fp);

      $ColumnNames = explode(',', $HeaderLine);

      // Loop through the columns and buld up a tabledef.
      $Defs = array();
      foreach ($ColumnNames as $ColumnName) {
         $ColumnName = trim($ColumnName);
         if (!$ColumnName) {
            continue;
         }

         // Check for duplicate filenames.
         $ColumnName2 = $ColumnName;
         for($i = 1; isset($Defs[$ColumnName2]); $i++) {
            $ColumnName2 = "DUP{$i}_{$ColumnName}";
         }
         $ColumnName = $ColumnName2;

         $Defs[$ColumnName] = $ColumnName.' varchar(200)'; // default column def.

         // Check to see if there is a more specific column definition.
         foreach ($ColumnInfo as $Expr => $ColumnDef) {
            if ($Expr == $ColumnName) {
               $Defs[$ColumnName] = $ColumnName.' '.$ColumnDef;
               break;
            } elseif ($Expr[0] == $Expr[strlen($Expr) - 1]) {
               if (preg_match($Expr, $ColumnName)) {
                  $Defs[$ColumnName] = $ColumnName.' '.$ColumnDef;
                  break;
               }
            }
         }
      }

      // Drop the table.
      $this->Ex->Query("drop table if exists `$TableName`");

      // Create the table.
      $CreateDef = "create table `$TableName` (\n".implode(",\n", $Defs).')';
      $this->Ex->Query($CreateDef, TRUE);
   }
}
?>