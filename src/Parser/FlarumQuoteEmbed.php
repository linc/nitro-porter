<?php

namespace Porter\Parser;

use Porter\Formatter;
use nadar\quill\BlockListener;
use nadar\quill\Lexer;
use nadar\quill\Line;

/**
 * Convert a Quill (Vanilla) external embed of a quote.
 *
 * @see
 */
class FlarumQuoteEmbed extends EmbedExternalListener
{
    /**
     * {@inheritDoc}
     */
    public function process(Line $line)
    {
        $embed = $line->insertJsonKey('embed-external');
        if ($embed && $this->isEmbedExternal($embed, 'quote')) {
            $data = [
                'url' => $embed['data']['url'] ?? '',
                'postid' => $embed['data']['attributes']['commentID'] ?? '',
                'authorid' => $embed['data']['attributes']['insertUser']['userID'] ?? '',
                'author' => $embed['data']['attributes']['insertUser']['name'] ?? '',
                'body' => $embed['data']['body'] ?? '',
            ];
            $this->pick($line, $data);
            $line->setDone();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function render(Lexer $lexer)
    {
        $this->wrapElement('&gt; <POSTMENTION displayname="{author}" id="{postid}">' .
            '@"{author}"#p{postid}</POSTMENTION> {body}<br />', ['author', 'postid', 'body']);
    }
}
