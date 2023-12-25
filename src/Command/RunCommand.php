<?php

namespace Porter\Command;

use Porter;
use Ahc\Cli\Input\Command;
use Ahc\Cli\IO\Interactor;
use Porter\Support;

class RunCommand extends Command
{
    public function __construct()
    {
        parent::__construct('run', 'Run a migration.');
        $this
            ->option('-s --source', 'Name of source package')
            ->option('-t --target', 'Name of target package')
            ->option('-i --input', 'Source connection (defined in config)')
            ->option('-o --output', 'Target connection (defined in config)')
            ->option('-x --sourceprefix', 'Source prefix')
            ->option('--cdn', 'CDN prefix')
            ->option('--dumpsql', 'Output SQL instead of migrating')
            //->option('-y --targetprefix', 'Target prefix')
            //->option('-l --limit', 'Limits export to specified data')
            ->usage(
                '<bold>  run</end> <comment><source> <source-connection> <target> <target-connection></end>' .
                ' ## Migrate data from source to target<eol/>'
            );
    }

    /**
     * Prompts for the user to collect required information.
     */
    public function interact(Interactor $io): void
    {
        if (!$this->source) {
            $this->set('source', $io->prompt('Source package'));
        }

        if (!$this->target) {
            $this->set('target', $io->prompt('Target package'));
        }

        if (!$this->input) {
            $this->set('input', $io->prompt('Source connection (from config)'));
        }

        if (!$this->output && $this->source !== 'file') {
            $this->set('output', $io->prompt('Target connection (from config)'));
        }
    }

    /**
     * Command execution.
     */
    public function execute()
    {
        // @todo validate
        $request = \Porter\Request::instance();
        $request->load([
            // @todo fix this name madness in Request object.
            'output' => $this->target,
            'package' => $this->source,
            'source' => $this->input,
            'target' => $this->output,
            'src-prefix' => $this->sourceprefix,
            'tables' => $this->tables,
            'dumpsql' => $this->dumpsql,
            'cdn' => $this->cdn,
        ]);

        \Porter\Controller::run($request);
    }

    /**
     * @param bool $sections
     * @return array
     */
    public function getAllOptions($sections = false): array
    {
        $options['package']['Values'] = array_keys(Support::getInstance()->getSources());
        $globalOptions = $options;
        $supported = Support::getInstance()->getSources();
        $result = [];

        if ($sections) {
            $result['Run Options'] = $globalOptions;
        } else {
            $result = $globalOptions;
        }

        foreach ($supported as $type => $options) {
            $commandLine = v('options', $options);
            if (!$commandLine) {
                continue;
            }

            if ($sections) {
                $result[$options['name']] = $commandLine;
            } else {
                // We need to add the types to each command line option for validation purposes.
                foreach ($commandLine as $longCode => $row) {
                    if (isset($result[$longCode])) {
                        $result[$longCode]['Packages'][] = $type;
                    } else {
                        $row['Packages'] = array($type);
                        $result[$longCode] = $row;
                    }
                }
            }
        }

        return $result;
    }
}
