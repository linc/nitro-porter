<?php

/**
 * Filter functions for passing thru values during export.
 *
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

/**
 * Available filter for ExportModel.
 *
 * @see ExportModel::filterData()
 * @see \Porter\Target\Flarum::comments()
 *
 * @param ?string $value
 * @param string $column
 * @param array $row
 * @return string
 */
function filterFlarumContent(?string $value, string $column, array $row): string
{
    $format = $row['Format'] ?? 'Text'; // Apparently null 'Format' values are possible.
    return \Porter\Formatter::instance()->toTextFormatter($format, $value);
}

/**
 * De-duplicate deleted usernames.
 *
 * Check for '[Deleted User]' (and variants) as username and replace.
 * Violates a unique key constraint on the target db's username field.
 *
 * @param string $value
 * @param string $column
 * @param array $row
 * @return string
 */
function fixDuplicateDeletedNames(string $value, string $column, array $row): string
{
    $duplicates = ['[Deleted User]', '[DeletedUser]', '-Deleted-User-'];
    if (in_array($value, $duplicates)) {
        $value = 'deleted_user_' . $row['UserID'];
    }
    return $value;
}

/**
 * @param mixed $value
 * @param string $column
 * @param array $row
 * @return string
 */
function fixNullEmails($value, string $column, array $row): string
{
    if (empty($value)) {
        $value = 'blank_email_' . $row['UserID'];
    }
    return $value;
}

/**
 * @param mixed $value
 * @param string $column
 * @param array $row
 * @return string
 */
function createDiscussionSlugs($value, string $column, array $row): string
{
    return $value; // @todo Create a slug
}

/**
 * Don't allow zero-equivalent dates.
 *
 * @param string $value
 * @return string
 */
function forceDate(string $value): string
{
    if (!$value || preg_match('`0000-00-00`', $value)) {
        return gmdate('Y-m-d H:i:s');
    }

    return $value;
}

/**
 * Only allow IPv4 addresses to pass.
 *
 * @param string $ip
 * @return string|null Valid IPv4 address or nuthin'.
 */
function forceIP4(string $ip): ?string
{
    $ip = null;
    if (preg_match('`(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})`', $ip, $m)) {
        $ip = $m[1];
    }

    return $ip;
}

/**
 * Creates URL codes containing only lowercase Roman letters, digits, and hyphens.
 * Converted from Gdn_Format::Url
 *
 * @param  string $str A string to be formatted.
 * @return string
 */
function formatUrl($str)
{
    $urlTranslations = array(
        '–' => '-',
        '—' => '-',
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'Å' => 'A',
        'Ā' => 'A',
        'Ą' => 'A',
        'Ă' => 'A',
        'Æ' => 'Ae',
        'Ç' => 'C',
        'Ć' => 'C',
        'Č' => 'C',
        'Ĉ' => 'C',
        'Ċ' => 'C',
        'Ď' => 'D',
        'Đ' => 'D',
        'Ð' => 'D',
        'È' => 'E',
        'É' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Ē' => 'E',
        'Ě' => 'E',
        'Ĕ' => 'E',
        'Ė' => 'E',
        'Ĝ' => 'G',
        'Ğ' => 'G',
        'Ġ' => 'G',
        'Ģ' => 'G',
        'Ĥ' => 'H',
        'Ħ' => 'H',
        'Ì' => 'I',
        'Í' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ī' => 'I',
        'Ĩ' => 'I',
        'Ĭ' => 'I',
        'Į' => 'I',
        'İ' => 'I',
        'Ĳ' => 'IJ',
        'Ĵ' => 'J',
        'Ķ' => 'K',
        'Ł' => 'K',
        'Ľ' => 'K',
        'Ĺ' => 'K',
        'Ļ' => 'K',
        'Ŀ' => 'K',
        'Ñ' => 'N',
        'Ń' => 'N',
        'Ň' => 'N',
        'Ņ' => 'N',
        'Ŋ' => 'N',
        'Ò' => 'O',
        'Ó' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'Oe',
        'Ō' => 'O',
        'Ő' => 'O',
        'Ŏ' => 'O',
        'Œ' => 'OE',
        'Ŕ' => 'R',
        'Ŗ' => 'R',
        'Ś' => 'S',
        'Š' => 'S',
        'Ş' => 'S',
        'Ŝ' => 'S',
        'Ť' => 'T',
        'Ţ' => 'T',
        'Ŧ' => 'T',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'Ū' => 'U',
        'Ü' => 'Ue',
        'Ů' => 'U',
        'Ű' => 'U',
        'Ŭ' => 'U',
        'Ũ' => 'U',
        'Ų' => 'U',
        'Ŵ' => 'W',
        'Ý' => 'Y',
        'Ŷ' => 'Y',
        'Ÿ' => 'Y',
        'Ź' => 'Z',
        'Ž' => 'Z',
        'Ż' => 'Z',
        'Þ' => 'T',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'ae',
        'å' => 'a',
        'ā' => 'a',
        'ą' => 'a',
        'ă' => 'a',
        'æ' => 'ae',
        'ç' => 'c',
        'ć' => 'c',
        'č' => 'c',
        'ĉ' => 'c',
        'ċ' => 'c',
        'ď' => 'd',
        'đ' => 'd',
        'ð' => 'd',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ē' => 'e',
        'ę' => 'e',
        'ě' => 'e',
        'ĕ' => 'e',
        'ė' => 'e',
        'ƒ' => 'f',
        'ĝ' => 'g',
        'ğ' => 'g',
        'ġ' => 'g',
        'ģ' => 'g',
        'ĥ' => 'h',
        'ħ' => 'h',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ī' => 'i',
        'ĩ' => 'i',
        'ĭ' => 'i',
        'į' => 'i',
        'ı' => 'i',
        'ĳ' => 'ij',
        'ĵ' => 'j',
        'ķ' => 'k',
        'ĸ' => 'k',
        'ł' => 'l',
        'ľ' => 'l',
        'ĺ' => 'l',
        'ļ' => 'l',
        'ŀ' => 'l',
        'ñ' => 'n',
        'ń' => 'n',
        'ň' => 'n',
        'ņ' => 'n',
        'ŉ' => 'n',
        'ŋ' => 'n',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'oe',
        'ø' => 'o',
        'ō' => 'o',
        'ő' => 'o',
        'ŏ' => 'o',
        'œ' => 'oe',
        'ŕ' => 'r',
        'ř' => 'r',
        'ŗ' => 'r',
        'š' => 's',
        'ù' => 'u',
        'ú' => 'u',
        'û' => 'u',
        'ū' => 'u',
        'ü' => 'ue',
        'ů' => 'u',
        'ű' => 'u',
        'ŭ' => 'u',
        'ũ' => 'u',
        'ų' => 'u',
        'ŵ' => 'w',
        'ý' => 'y',
        'ÿ' => 'y',
        'ŷ' => 'y',
        'ž' => 'z',
        'ż' => 'z',
        'ź' => 'z',
        'þ' => 't',
        'ß' => 'ss',
        'ſ' => 'ss',
        'А' => 'A',
        'Б' => 'B',
        'В' => 'V',
        'Г' => 'G',
        'Д' => 'D',
        'Е' => 'E',
        'Ё' => 'YO',
        'Ж' => 'ZH',
        'З' => 'Z',
        'Й' => 'Y',
        'К' => 'K',
        'Л' => 'L',
        'М' => 'M',
        'Н' => 'N',
        'О' => 'O',
        'П' => 'P',
        'Р' => 'R',
        'С' => 'S',
        'ș' => 's',
        'ț' => 't',
        'Ț' => 'T',
        'Т' => 'T',
        'У' => 'U',
        'Ф' => 'F',
        'Х' => 'H',
        'Ц' => 'C',
        'Ч' => 'CH',
        'Ш' => 'SH',
        'Щ' => 'SCH',
        'Ъ' => '',
        'Ы' => 'Y',
        'Ь' => '',
        'Э' => 'E',
        'Ю' => 'YU',
        'Я' => 'YA',
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'yo',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'y',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'c',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'sch',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya'
    );

    // Preliminary decoding
    $str = strip_tags(html_entity_decode($str, ENT_COMPAT, 'UTF-8'));
    $str = strtr($str, $urlTranslations);
    $str = preg_replace('`[\']`', '', $str);

    // Test for Unicode PCRE support
    // On non-UTF8 systems this will result in a blank string.
    $unicodeSupport = (preg_replace('`[\pP]`u', '', 'P') != '');

    // Convert punctuation, symbols, and spaces to hyphens
    if ($unicodeSupport) {
        $str = preg_replace('`[\pP\pS\s]`u', '-', $str);
    } else {
        $str = preg_replace('`[\s_[^\w\d]]`', '-', $str);
    }

    // Lowercase, no trailing or repeat hyphens
    $str = preg_replace('`-+`', '-', strtolower($str));
    $str = trim($str, '-');

    return rawurlencode($str);
}

/**
 * Decode the HTML out of a value.
 */
function HTMLDecoder($value)
{
    $characterSet = (defined('PORTER_CHARACTER_SET')) ? PORTER_CHARACTER_SET : 'UTF-8';

    switch ($characterSet) {
        case 'latin1':
            $characterSet = 'ISO-8859-1';
            break;
        case 'latin9':
            $characterSet = 'ISO-8859-15';
            break;
        case 'utf8':
        case 'utf8mb4':
            $characterSet = 'UTF-8';
            break;
    }

    return html_entity_decode($value, ENT_QUOTES, $characterSet);
}

/**
 * Inverse int value.
 *
 * @param mixed $value
 * @return int
 */
function notFilter($value)
{
    return (int)(!$value);
}

/**
 * Convert a timestamp to MySQL date format.
 *
 * Do this in MySQL with FROM_UNIXTIME() instead whenever possible.
 *
 * @param mixed $value
 * @return null|string
 */
function timestampToDate($value)
{
    if ($value == null) {
        return null;
    } else {
        return gmdate('Y-m-d H:i:s', $value);
    }
}

/**
 * Wrapper for long2ip that nulls 'non-digit' values.
 *
 * @param mixed $value
 * @return null|string
 */
function long2ipf($value)
{
    if (!ctype_digit($value)) {
        return null;
    }

    return long2ip($value);
}

/**
 * Convert 'y/n' to boolean.
 *
 * @param mixed $value
 * @return int
 */
function YNBool($value)
{
    if ($value == 'y') {
        return 1;
    } else {
        return 0;
    }
}

/**
 * Guess the Format of the Body.
 *
 * @param mixed $value
 * @return string
 */
function guessFormat($value)
{
    if (strpos($value, '[') !== false) {
        return 'BBCode';
    } elseif (strpos($value, '<') !== false) {
        return 'Html';
    } else {
        return 'BBCode';
    }
}

/**
 * Derive mimetype from file extension.
 *
 * @param string $value
 * @return string
 */
function mimeTypeFromExtension($value)
{

    if (strpos($value, '.') === 0) {
        $value = substr($value, 1);
    }

    switch ($value) {
        case 'png':
        case 'jpg':
        case 'jpeg':
        case 'gif':
        case 'bmp':
            return 'image/' . $value;
        case 'zip':
        case 'doc':
        case 'docx':
        case 'pdf':
        case 'xls':
        case 'swf':
            return 'application/' . $value;
        case 'txt':
        case 'htm':
        case 'html':
            return 'text/' . $value;
        case 'mov':
        case 'avi':
            return 'video/' . $value;
    }
    return '';
}

/**
 * Change square brackets to braces.
 *
 * @param mixed $value
 * @return mixed
 */
function cleanBodyBrackets($value)
{
    if (strpos($value, '[') !== false) {
        $result = str_replace(array('<', '>'), array('[', ']'), $value);
        return $result;
    }
    return $value;
}

/**
 * @param string $text
 * @return string
 */
function bbPressTrim($text)
{
    return rtrim(bb_Code_Trick_Reverse($text));
}

/**
 * @param string $text
 * @return string
 */
function bb_Code_Trick_Reverse($text)
{
    $text = preg_replace_callback("!(<pre><code>|<code>)(.*?)(</code></pre>|</code>)!s", 'bb_decodeit', $text);
    $text = str_replace(array('<p>', '<br />'), '', $text);
    $text = str_replace('</p>', "\n", $text);
    $text = str_replace('<coded_br />', '<br />', $text);
    $text = str_replace('<coded_p>', '<p>', $text);
    $text = str_replace('</coded_p>', '</p>', $text);

    return $text;
}

/**
 * @param array $matches
 * @return string
 */
function bb_Decodeit($matches)
{
    $text = $matches[2];
    $trans_table = array_flip(get_html_translation_table(HTML_ENTITIES));
    $text = strtr($text, $trans_table);
    $text = str_replace('<br />', '<coded_br />', $text);
    $text = str_replace('<p>', '<coded_p>', $text);
    $text = str_replace('</p>', '</coded_p>', $text);
    $text = str_replace(array('&#38;', '&amp;'), '&', $text);
    $text = str_replace('&#39;', "'", $text);
    if ('<pre><code>' == $matches[1]) {
        $text = "\n$text\n";
    }

    return "`$text`";
}
