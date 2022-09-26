<?php

namespace Porter\Parser\Flarum;

use nadar\quill\Lexer;
use nadar\quill\Line;
use Porter\Parser\EmbedExternalListener;

/**
 * Convert a Quill (Vanilla) external embed of a link.
 *
 * @see https://s9etextformatter.readthedocs.io/Plugins/Autolink/Synopsis/
 */
class LinkEmbed extends EmbedExternalListener
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
