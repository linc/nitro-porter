<?php

namespace Porter;

class Router
{
    public static function run($request): callable
    {
        switch (true) {
            case $request->get('list'): // Single package feature list.
                return '\Porter\Render::viewFeatureList';
            case $request->get('features'): // Overview table.
                return '\Porter\Render::viewFeatureTable';
            case $request->get('help'): // CLI help.
                return '\Porter\Render::cliHelp';
            case $request->get('package'): // Main export process.
                return '\Porter\Controller::run';
            default: // Starting Web UI (index).
                return '\Porter\Render::viewForm';
        }
    }
}
