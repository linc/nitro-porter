<?php

namespace Porter;

use Porter\Database\DbFactory;

class ExportFactory
{
    public static function build(): \Porter\ExportController
    {
        // Wire new database.
        $config = loadConfig();
        $dbConfig = $config['connections']['databases'][0]; // @todo
        bootDatabase($dbConfig);

        // Get the package controller.
        $package = getValidPackage($_POST['package']);

        // Wire old database / model mess.
        $package->loadPrimaryDatabase();
        $package->handleInfoForm();
        $dbfactory = new DbFactory($package->getDbInfo(), 'pdo');
        $model = new ExportModel($dbfactory);
        $model->controller = $package;
        $model->prefix = '';
        $package->setModel($model);

        // Legacy construct.
        $package->build();

        return $package;
    }
}
