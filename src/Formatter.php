<?php

namespace Porter;

use s9e\TextFormatter\Bundles\Forum as BBCode;
use s9e\TextFormatter\Bundles\Fatdown as Markdown;

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
            case 'Raw':
                return self::wrap('r', $text);
            case 'BBCode':
                return BBCode::parse($text);
            case 'Markdown':
                return Markdown::parse($text);
            case 'Text':
            case 'TextEx':
            default:
                return self::wrap('t', $text);
        }
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
