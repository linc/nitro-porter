<?php

namespace Porter\Parser;

use Porter\Formatter;
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
            // Quotes can be nested / recursive in any format.
            // If the 'body' isn't null, just use that. Otherwise, we have to go deeper.
            $body = $embed['data']['body'] ?? $this->processRawQuote($embed);
            $data = [
                'url' => $embed['data']['url'] ?? '',
                'postid' => $embed['data']['attributes']['commentID'] ?? '',
                'authorid' => $embed['data']['attributes']['insertUser']['userID'] ?? '',
                'author' => $embed['data']['attributes']['insertUser']['name'] ?? '',
                'body' => $body,
            ];
            $this->pick($line, $data);
            $line->setDone();
        }
    }

    /**
     * Recursively process embedded quotes that are Rich text.
     *
     * @param array $embed
     * @return string
     */
    public function processRawQuote(array $embed): string
    {
        $body = '';
        if (isset($embed['data']['attributes']['bodyRaw'])) {
            $text = json_encode($embed['data']['attributes']['bodyRaw']);
            $format = $embed['data']['attributes']['format'] ?? 'Rich';
            $body = Formatter::instance()->toTextFormatter($format, $text);
            $body = Formatter::unwrap($body); // Undo 'r' XML tag from parsing since we're mid-post.
        }
        return $body;
    }

    /**
     * {@inheritDoc}
     */
    public function render(Lexer $lexer)
    {
        // Carefully mimic the TextFormatter output for quote replies.
        // Omits 'p' tags that wrap <POSTMENTION> thru {body} normally because we don't know if the body has 'p' tags.
        $this->wrapElement('<QUOTE><i>&gt; </i>' .
            // Postscript will fill in blank attributes.
            '<POSTMENTION discussionid="" displayname="{author}" id="{postid}" number="">' .
            '@"{author}"#p{postid}</POSTMENTION> {body}</QUOTE>', ['author', 'postid', 'body']);
    }
}
