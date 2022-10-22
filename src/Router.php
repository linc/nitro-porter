<?php

namespace Porter;

class Router
{
    public static function run($request): callable
    {
        switch (true) {
            case $request->get('showsource'): // Single source feature list.
            case $request->get('showtarget'): // Single target feature list.
                return '\Porter\Render::viewFeatureList';
            case $request->get('sources'): // Overview table.
                return '\Porter\Render::viewSourcesTable';
            case $request->get('targets'): // Overview table.
                return '\Porter\Render::viewTargetsTable';
            case $request->get('help'): // CLI help.
                return '\Porter\Render::cliHelp';
            case $request->get('package'): // Main export process.
                return '\Porter\Controller::run';
            default: // Starting Web UI (index).
                return '\Porter\Render::viewForm';
        }
    }
}
