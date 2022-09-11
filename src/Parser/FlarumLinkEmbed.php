<?php

namespace Porter\Parser;

use nadar\quill\BlockListener;
use nadar\quill\Lexer;
use nadar\quill\Line;

/**
 * Convert a Quill (Vanilla) external embed of a link.
 *
 * @see https://s9etextformatter.readthedocs.io/Plugins/Autolink/Synopsis/
 */
class FlarumLinkEmbed extends BlockListener
{
    /**
     * {@inheritDoc}
     */
    public function process(Line $line)
    {
        $embed = $line->insertJsonKey('embed-external');
        if (
            $embed && isset($embed['data']['url'])
            && isset($embed['data']['type'])
            && $embed['data']['type'] === 'link' // Key logic difference from FlarumImageEmbed
        ) {
            $this->pick($line, ['url' => $embed['data']['url']]);
            $line->setDone();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function render(Lexer $lexer)
    {
        $this->wrapElement(' <a href="{url}">{url}</a> ', ['url']);
    }
}
