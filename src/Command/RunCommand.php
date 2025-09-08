<?php

namespace Porter\Command;

use Porter;
use Ahc\Cli\Input\Command;
use Ahc\Cli\IO\Interactor;
use Porter\Config;
use Porter\Support;

class RunCommand extends Command
{
    public function __construct()
    {
        parent::__construct('run', 'Run a migration.');
        $this
            ->option('-s --source', 'Source package alias (or "port")')
            ->option('-t --target', 'Target package alias (or "sql" or "file")')
            ->option('-i --input', 'Source connection alias (defined in config)')
            ->option('-o --output', 'Target connection alias (defined in config)')
            ->option('--sp', 'Source table prefix (override package default)')
            ->option('--tp', 'Target table prefix (override package default)')
            ->option('--cdn', 'CDN prefix')
            ->option('-d --data', 'Limit to specified data types (CSV)')
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
        if (!$this->source && !Config::getInstance()->get('source')) {
            $this->set('source', $io->prompt('Source package alias (see `porter list -n=sources`)'));
        }

        if (!$this->target && !Config::getInstance()->get('target')) {
            $this->set('target', $io->prompt('Target package alias (see `porter list -n=targets`)'));
        }

        if (!$this->input && !Config::getInstance()->get('input_alias')) {
            $this->set('input', $io->prompt('Input connection alias (see config.php)'));
        }

        if (!$this->output && $this->source !== 'file' && !Config::getInstance()->get('output_alias')) {
            $this->set('output', $io->prompt('Output connection alias (see config.php)'));
        }
    }

    /**
     * Command execution.
     *
     * @throws \Exception
     */
    public function execute(): void
    {
        $request = new \Porter\Request(
            $this->source,
            $this->target,
            $this->input,
            $this->output,
            $this->sp,
            $this->tp,
            $this->cdn,
            $this->data
        );

        runPorter($request);
    }

    /**
     * @param bool $sections
     * @return array
     */
    public function getAllOptions(bool $sections = false): array
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
