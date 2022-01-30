<?php

namespace Porter;

use Porter\Database\DbFactory;

class ExportFactory
{
    public static function build(): \Porter\ExportController
    {
        // Wire new database.
        $source = \Porter\Request::instance()->get('source');
        $connection = new Connection($source);
        $info = $connection->getAllInfo();
        bootDatabase($info);

        // Get the package controller.
        $package_name = \Porter\Request::instance()->get('package');
        $package = getValidPackage($package_name);

        // Wire old database / model mess.
        $package->loadPrimaryDatabase($info);
        $package->handleInfoForm();
        $dbfactory = new DbFactory($package->getDbInfo(), 'pdo');
        $model = new ExportModel($dbfactory);
        $model->prefix = '';

        // Set the database source prefix.
        $supported = PackageSupport::getInstance()->get();
        $hasDefaultPrefix = !empty($supported[$package_name]['prefix']);

        if (isset($package->dbInfo['prefix'])) {
            if ($package->dbInfo['prefix'] === 'PACKAGE_DEFAULT') {
                if ($hasDefaultPrefix) {
                    $model->prefix = $supported[$package_name]['prefix'];
                }
            } else {
                $model->prefix = $package->dbInfo['prefix'];
            }
        }

        // Set model properties.
        $model->destination = $package->param('dest', 'file');
        $model->destDb = $package->param('destdb', null);
        $model->testMode = $package->param('test', false);

        /**
         * Selective exports
         * 1. Get the comma-separated list of tables and turn it into an array
         * 2. Trim off the whitespace
         * 3. Normalize case to lower
         * 4. Save to the ExportModel instance
         */
        $restrictedTables = $package->param('tables', false);
        if (!empty($restrictedTables)) {
            $restrictedTables = explode(',', $restrictedTables);

            if (is_array($restrictedTables) && !empty($restrictedTables)) {
                $restrictedTables = array_map('trim', $restrictedTables);
                $restrictedTables = array_map('strtolower', $restrictedTables);

                $model->restrictedTables = $restrictedTables;
            }
        }

        $package->setModel($model);

        return $package;
    }
}
