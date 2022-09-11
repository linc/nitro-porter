<?php

namespace Porter\Parser;

use nadar\quill\BlockListener;
use nadar\quill\Lexer;
use nadar\quill\Line;

/**
 * Convert a Quill (Vanilla) external embed of an uploaded image.
 *
 * @see https://s9etextformatter.readthedocs.io/Plugins/MediaEmbed/Synopsis/
 */
class FlarumImageEmbed extends BlockListener
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
            && $embed['data']['type'] === 'image'
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
        $this->wrapElement('<UPL-IMAGE-PREVIEW url="{url}">' .
            '[upl-image-preview url={url}]' .
            '</UPL-IMAGE-PREVIEW>', ['url']);
    }
}
