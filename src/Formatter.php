<?php

namespace Porter;

use s9e\TextFormatter\Bundles\Forum as BBCode;
use s9e\TextFormatter\Bundles\Fatdown as Markdown;
use nadar\quill\Lexer as Quill;

class Formatter
{
    /** @var ?Formatter Singleton storage. */
    private static ?Formatter $instance = null;

    /** @var array Some formatting requires UserIDs to be accessible. */
    protected array $userMap = [];

    /**
     * Singleton accessor.
     *
     * @return Formatter
     */
    public static function instance($ex = null): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->setup($ex);
        }
        return self::$instance;
    }

    /**
     * @return void
     */
    public function setup(ExportModel $ex)
    {
        $this->userMap = $ex->buildUserMap();
    }

    /**
     * Put content in TextFormatter-compatible format.
     *
     * @param ?string $format
     * @param ?string $text
     * @return string
     */
    public function toTextFormatter(?string $format, ?string $text): string
    {
        switch ($format) {
            case 'Html':
            case 'Wysiwyg':
            case 'Raw': // Unfiltered
                return self::wrap('r', $this->fixRawMentions($text));
            case 'BBCode':
                return $this->fixRawMentions(BBCode::parse($text));
            case 'Markdown':
                return $this->fixRawMentions(Markdown::parse($text));
            case 'Rich': // Quill
                return self::wrap('r', self::dequill($text));
            case 'Text':
            case 'TextEx':
            default:
                // Use of nl2br() here is needed for Vanilla PMs (which have no `Format`).
                // May require more refined detection for other cases but too many breaks is safer than too few.
                return self::wrap('t', $this->fixRawMentions(nl2br($text)));
        }
    }

    /**
     * 'Rich' format in Vanilla is actually Quill WYSIWYG Delta.
     *
     * Vanilla stored invalid JSON — the "ops" array without its wrapper.
     *
     * @see https://quilljs.com/docs/delta/
     *
     * @param string $text
     * @return string
     */
    public static function dequill(string $text): string
    {
        // Fix invalid Quill Delta.
        $text = self::fixQuillHeaders($text);

        // Fix the JSON.
        $text = '{"ops":' . $text . '}';

        // Use the Quill renderer.
        $lexer = new Quill($text);

        // Custom mention handler.
        $lexer->registerListener(new \Porter\Parser\FlarumMention());

        // Custom image embed handler for `embed-external`.
        $lexer->registerListener(new \Porter\Parser\FlarumImageEmbed());

        // Custom link handler for `embed-external`.
        $lexer->registerListener(new \Porter\Parser\FlarumLinkEmbed());

        // Custom quote handler for `embed-external`.
        $lexer->registerListener(new \Porter\Parser\FlarumQuoteEmbed());

        return $lexer->render();
    }

    /**
     * Vanilla appears to use a customized 'header' element in Quill Deltas that breaks parsers.
     *
     * @todo Replace this with an overridden listener.
     * @todo example call: `$lexer->overwriteListener(new Heading, new \Porter\Parser\Heading());`
     * @todo example class: `class Heading extends \nadar\quill\listener\Heading`
     *
     * @param string $text
     * @return string
     */
    public static function fixQuillHeaders(string $text): string
    {
        // Avoid regex if we can.
        if (strstr($text, '{"header"') === false) {
            return $text;
        }

        // Remove array of attributes under `header` and simply give the numeric level instead.
        // ex: {"header":{"level":1,"ref":""}},
        return preg_replace('/{"header":{"level":([1-6]),"ref":"\w*"}}/', '{"header":$1}', $text);
    }

    /**
     * Replace basic Vanilla mentions with tag-based Flarum mentions.
     *
     * @param ?string $text
     * @return string
     */
    public function fixRawMentions(?string $text): string
    {
        // Allow empty content.
        if (is_null($text)) {
            return '';
        }

        // Find unconverted mentions and associate userID.
        $mentions = $this->findRawMentions($text);
        foreach ($mentions as $mention) {
            // Remove the optional double quote if present & guarantee we have a userid.
            $slug = strtolower(trim($mention, "\""));
            if (!isset($this->userMap[$slug])) {
                continue; // Username wasn't in the map, abort.
            }

            // Do the content substitution per found mention.
            $newMention = '<USERMENTION id="' . $this->userMap[$slug] . '">@' . $mention . '</USERMENTION>';
            $text = str_replace('@' . $mention, $newMention, $text);
        }

        return $text;
    }

    /**
     * Find valid Vanilla mentions in a post's content.
     *
     * Results may be wrapped in double quotes if the original was.
     *
     * @param string $content
     * @return array
     */
    protected function findRawMentions(string $content): array
    {
        $mentions = [];
        preg_match_all(
            // Mentions start with '@' and may be quoted or not.
            // Valid username rules apply unless it's quoted, in which case ANY character is allowed.
            // Mentions are bounded by whitespace, non-dash/underscore punctuation, OR the start/end of content.
            '/(?:^|[\s\r\n])@(([\p{N}\p{L}\p{M}\p{Pc}\p{Pd}]+)(?=[\s\r\n\p{Po}\p{Ps}\p{Pe}]+|$)|(".+"))/Uu',
            $content,
            $mentions
        );
        return $mentions[1];
    }

    /**
     * Wraps text in an XML tag.
     *
     * s9e\TextFormatter requires a `<t>` wrap for plain text and `<r>` for HTML ('rich').
     *
     * @param string $char
     * @param string $text
     * @return string
     */
    public static function wrap(string $char, string $text): string
    {
        return '<' . $char . '>' . $text . '</' . $char . '>';
    }
}
