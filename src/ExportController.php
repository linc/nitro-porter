<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace Porter;

/**
 * Generic controller implemented by forum-specific ones.
 */
class ExportController
{
    /**
     * Export workflow.
     */
    public static function doExport(Package $package, ExportModel $model)
    {
        // Test src tables' existence & structure.
        $model->verifySource($package->sourceTables);

        // Start export.
        set_time_limit(0);
        if (isset($package::getSupport()['charset_table'])) { // @todo Use a wrapper in Package
            $model->setCharacterSet($package::getSupport()['charset_table']);
        }
        $model->beginExport($package::getSupport()['name']);
        $package->exportModel = $model; // @todo
        $package->run($model);
        $model->endExport();
    }

    /**
     * Called by router to setup and run main export process.
     */
    public static function run(Request $request)
    {
        // Wire new database.
        $source = $request->get('source');
        $connection = new Connection($source);
        $info = $connection->getAllInfo();
        bootDatabase($info);

        // Get model.
        $model = modelFactory($request, $info); // @todo Pass options not Request

        // Get package.
        $package_name = $request->get('package');
        $package = packageFactory($package_name);

        // Main process.
        self::doExport($package, $model);

        // Write the results.  Send no path if we don't know where it went.
        $relativePath = $request->get('destpath') ?? $model->path;
        Render::viewExportResult($model->comments, 'Info', $relativePath);
    }
}
