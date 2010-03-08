<?php
/**
 * Views for Vanilla 2 export tools
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */
 
class ExportViews {
   
   public function PageHeader() {
   
      ?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
   <title>Vanilla 2 forum export tool</title>
   <style>
   
   </style>
</head>
<body>
<?php

   }
   
   
   public function PageFooter() {
   
      ?>
</body>
</html><?php

   }
   
   public function InfoForm() {
      $this->PageHeader();
      ?>
      
      <?php
      $this->PageFooter();
   }
   
   public function ExportResult() {
      $this->PageHeader();
      ?>
      
      <?php
      $this->PageFooter();
      
   }
   
   
}