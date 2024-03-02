<?php

namespace Porter\Command;

use Porter;
use Ahc\Cli\Input\Command;
use Ahc\Cli\IO\Interactor;
use Ahc\Cli\Output\Writer;

class ShowCommand extends Command
{
    public function __construct()
    {
        parent::__construct('show', 'Show feature support of a package.');
        $this
            ->argument('<type>', 'One of "source" or "target"')
            ->argument('<name>', 'Name of package.')
            ->usage(
                '<bold>  show</end> <comment>[type] [name]</end> ## Show features of a package.<eol/>' .
                '<bold>  show</end> <comment>source vanilla2</end> ' .
                '## Show what features can be migrated from Vanilla.<eol/>' .
                '<bold>  show</end> <comment>target flarum</end> ## Show what features can be migrated to Flarum.<eol/>'
            );
    }

    /**
     * Command execution.
     */
    public function execute()
    {
        // @todo validate
        self::viewFeatureList($this->type, $this->name);
    }

    /**
     * Output features for a single platform.
     *
     * @param string $type
     * @param string $name
     */
    public static function viewFeatureList(string $type, string $name)
    {
        if ($type === 'source') {
            $supported = \Porter\Support::getInstance()->getSources();
        } elseif ($type === 'target') {
            $supported = \Porter\Support::getInstance()->getTargets();
        }
        $features = \Porter\Support::getInstance()->getAllFeatures();
        foreach ($features as $feature => $trash) {
            $list[] = [
                'feature' => preg_replace('/[A-Z]/', ' $0', $feature),
                'support' =>  \Porter\Support::getInstance()->getFeatureStatusHtml($supported, $name, $feature)
            ];
        }

        $writer = new Writer();

        if ($type === 'target' && strtolower($name) === 'vanilla2') {
            $writer->bold->green->write("\n" . 'All features are supported for target Vanilla2.' . "\n");
        } elseif (!array_key_exists($name, $supported)) {
            $writer->bold->yellow->write("\n" . 'Unknown package "' . $name . '". Package is case-sensitive.' . "\n");
        } else {
            $writer->bold->green->write("\n" . 'Support for ' . $type . ' ' . $supported[$name]['name'] . "\n");
            $writer->table($list, ['head' => 'bold']);
        }
    }
}
