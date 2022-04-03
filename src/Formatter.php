<?php

namespace Porter;

use s9e\TextFormatter\Bundles\Forum as BBCode;
use s9e\TextFormatter\Bundles\Fatdown as Markdown;

class Formatter
{
    /** @var string[] Names of available formatters. */
    public const ALLOWED_FORMATS = [
        'BBCode' => 's9e\TextFormatter\Bundles\Forum',
        'Markdown' => 's9e\TextFormatter\Bundles\Fatdown',
    ];

    /**
     * Whether named formatter is available.
     *
     * @param string $format
     * @return bool
     */
    public static function validateFormat(string $format): bool
    {
        return array_key_exists($format, self::ALLOWED_FORMATS);
    }

    /**
     * Do the requested formatting.
     *
     * Returns the string unchanged if it isn't possible.
     *
     * @param string $format
     * @param string $text
     * @return string
     */
    public static function parse(string $format, string $text): string
    {
        // Skip if invalid format provided.
        if (!self::validateFormat($format)) {
            return $text;
        }

        // Making a class available with 'use' is a compile-time event.
        // Using a class name dynamically via variable is a run-time event.
        // Therefore we cannot "use x as BBCode" then ref "BBCode" dynamically as a class here, we need a lookup.
        $formatter = self::ALLOWED_FORMATS[$format];
        /** @var $formatter BBCode|Markdown */
        return $formatter::parse($text);
    }

    /**
     * Available filter for ExportModel.
     *
     * @see ExportModel::filterData()
     * @param string $value
     * @param array $row
     * @return string
     */
    public static function filterBody(string $value, array $row)
    {
        $format = $row['Format'] ?? 'Html'; // Apparently null 'Format' values are possible.
        return self::parse($format, $value);
    }
}
