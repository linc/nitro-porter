<?php

namespace Porter\Command;

use Porter;
use Ahc\Cli\Input\Command;
use Ahc\Cli\IO\Interactor;
use Ahc\Cli\Output\Writer;

class ListCommand extends Command
{
    public function __construct()
    {
        parent::__construct('list', 'List available packages of requested type.');
        $this
            ->option('-n --name', 'One of "sources", "targets", or "connections"')
            ->usage(
                '<bold>  list</end></end> ## Choose what to list interactively.<eol/>' .
                '<bold>  list</end> -n=targets</end> ## List available target packages.<eol/>'
            );
    }

    /**
     * Prompts for the user to collect required information.
     */
    public function interact(Interactor $io): void
    {
        if (!$this->name) {
            $lists = ['s' => 'sources', 't' => 'targets', 'c' => 'connections'];
            $choice = $io->choice('Select a list', $lists, '3');
            $this->set('name', $lists[$choice]);
        }
    }

    /**
     * Command execution.
     */
    public function execute()
    {
        switch ($this->name) {
            case 'sources':
            case 'targets':
                self::viewFeatureTable($this->name);
                break;
            case 'connections':
                self::viewConnections();
                break;
            default:
                $io = $this->app()->io();
                $io->write('Invalid value for <type>');
        }
    }

    /**
     * Output a list of connections to shell.
     */
    public static function viewConnections()
    {
        $writer = new Writer();
        $writer->bold->green->write("\n" . 'Supported Connections' . "\n");
        $writer->comment('List is from config.php' . "\n");
        foreach (\Porter\Config::getInstance()->getConnections() as $c) {
            $writer->write("\n");
            $writer->bold->write($c['alias']);
            $writer->write(' (' . $c['user'] . '@' . $c['name'] . ')');
        }
        $writer->write("\n\n");
    }

    /**
     * Output a list of supported platforms.
     *
     * @param string $type One of 'sources' or 'targets'.
     */
    public static function viewFeatureTable(string $type = 'sources')
    {
        if (!in_array($type, ['sources', 'targets'])) {
            return;
        }
        // Get the list.
        $method = 'get' . $type;
        $supported = \Porter\Support::getInstance()->$method();
        $packages = array_keys($supported);

        // Output
        $writer = new Writer();
        $writer->bold->green->write("\n" . 'Supported ' . ucfirst($type) . "\n");
        foreach ($packages as $package) {
            $writer->write("\n");
            $writer->bold->write($package);
            $writer->write(' (' . $supported[$package]['name'] . ')');
        }
        $writer->write("\n\n");
    }
}
