<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace Porter;

/**
 * Generic controller implemented by forum-specific ones.
 */
class Controller
{
    /**
     * Export workflow.
     */
    public static function doExport(Source $source, ExportModel $model)
    {
        // Test src tables' existence & structure.
        $model->verifySource($source->sourceTables);

        // Start export.
        set_time_limit(0);
        if (isset($source::getSupport()['charset_table'])) { // @todo Use a wrapper in Source
            $model->setCharacterSet($source::getSupport()['charset_table']);
        }
        $model->beginExport($source::getSupport()['name']);
        $source->exportModel = $model; // @todo
        $source->run($model);
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

        // Get source.
        $source = sourceFactory($request->get('package'));

        // Main process.
        self::doExport($source, $model);

        // Write the results.  Send no path if we don't know where it went.
        $relativePath = $request->get('destpath') ?? $model->path;
        Render::viewExportResult($model->comments, 'Info', $relativePath);
    }
}
