<?php

namespace Porter\Parser;

use nadar\quill\Lexer;
use nadar\quill\Line;

/**
 * Convert a Quill (Vanilla) external embed of a link.
 *
 * @see https://s9etextformatter.readthedocs.io/Plugins/Autolink/Synopsis/
 */
class FlarumLinkEmbed extends EmbedExternalListener
{
    /**
     * {@inheritDoc}
     */
    public function process(Line $line)
    {
        $embed = $line->insertJsonKey('embed-external');
        if ($embed && $this->isEmbedExternal($embed, 'link')) {
            $this->pick($line, ['url' => $embed['data']['url']]);
            $line->setDone();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function render(Lexer $lexer)
    {
        $this->wrapElement(' <a href="{url}">{url}</a><br/>', ['url']);
    }
}
