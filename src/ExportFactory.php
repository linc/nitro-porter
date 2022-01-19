<?php

namespace NitroPorter;

class ExportFactory
{
    public static function build(): \NitroPorter\ExportController
    {
        // Wire new database.
        $config = loadConfig();
        $dbConfig = $config['connections']['databases'][0]; // @todo
        bootDatabase($dbConfig);

        // Get the package controller.
        $package = getValidPackage($_POST['type']);

        return $package;
    }
}
