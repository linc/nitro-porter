<?php

namespace Porter;

use s9e\TextFormatter\Bundles\Forum as BBCode;
use s9e\TextFormatter\Bundles\Fatdown as Markdown;
use nadar\quill\Lexer as Quill;

class Formatter
{
    /**
     * Put content in TextFormatter-compatible format.
     *
     * @param string $format
     * @param string $text
     * @return string
     */
    public static function toTextFormatter(string $format, string $text): string
    {
        switch ($format) {
            case 'Html':
            case 'Wysiwyg':
            case 'Raw': // Unfiltered
                return self::wrap('r', $text);
            case 'BBCode':
                return BBCode::parse($text);
            case 'Markdown':
                return Markdown::parse($text);
            case 'Rich': // Quill
                return self::dequill($text);
            case 'Text':
            case 'TextEx':
            default:
                return self::wrap('t', $text);
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
        $lexer->registerListener(new \Porter\Parser\FlarumMention()); // Custom mention handler.
        $text = $lexer->render();

        // Wrap for TextFormatter.
        return self::wrap('r', $text);
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

    /**
     * Available filter for ExportModel.
     *
     * @see ExportModel::filterData()
     * @see \Porter\Target\Flarum::comments()
     *
     * @param string $value
     * @param array $row
     * @return string
     */
    public static function filterFlarumContent(string $value, array $row): string
    {
        $format = $row['Format'] ?? 'Text'; // Apparently null 'Format' values are possible.
        return self::toTextFormatter($format, $value);
    }
}
