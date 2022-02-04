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
        $connection = new Connection($request->get('source'));
        $info = $connection->getAllInfo();
        bootDatabase($info);

        // Export.
        $model = modelFactory($request, $info); // @todo Pass options not Request
        $source = sourceFactory($request->get('package'));
        self::doExport($source, $model);

        // Write the results (web only).
        if (!defined('CONSOLE')) {
            Render::viewResult($model->comments);
        }
    }
}
