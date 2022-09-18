<?php

namespace Porter\Parser;

use nadar\quill\Lexer;
use nadar\quill\Line;

/**
 * Convert a Quill (Vanilla) external embed of an uploaded image.
 *
 * @see https://s9etextformatter.readthedocs.io/Plugins/MediaEmbed/Synopsis/
 */
class FlarumImageEmbed extends EmbedExternalListener
{
    /**
     * {@inheritDoc}
     */
    public function process(Line $line)
    {
        $embed = $line->insertJsonKey('embed-external');
        if ($embed && ($this->isEmbedExternal($embed, 'image') || $this->isEmbedExternal($embed, 'giphy'))) {
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
