<?php
/**
 * Filter functions for passing thru values during export.
 *
 * @copyright 2009-2015 Vanilla Forums Inc.
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 * @package VanillaPorter
 */

/**
 * Don't allow zero-equivalent dates.
 *
 * @param $value
 * @return string
 */
function forceDate($Value) {
    if (!$Value || preg_match('`0000-00-00`', $Value)) {
        return gmdate('Y-m-d H:i:s');
    }

    return $Value;
}

/**
 * Only allow IPv4 addresses to pass.
 *
 * @param $ip
 * @return string|null Valid IPv4 address or nuthin'.
 */
function forceIP4($ip) {
    if (preg_match('`(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})`', $ip, $m)) {
        $ip = $m[1];
    } else {
        $ip = null;
    }

    return $ip;
}

/**
 * Creates URL codes containing only lowercase Roman letters, digits, and hyphens.
 * Converted from Gdn_Format::Url
 *
 * @param string $str A string to be formatted.
 * @return string
 */
function formatUrl($Str) {
    $UrlTranslations = array(
        '–' => '-',
        '—' => '-',
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'Ae',
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
        'Ț' => 'T',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'Ü' => 'Ue',
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
        'ü' => 'ue',
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
    $Str = strip_tags(html_entity_decode($Str, ENT_COMPAT, 'UTF-8'));
    $Str = strtr($Str, $UrlTranslations);
    $Str = preg_replace('`[\']`', '', $Str);

    // Test for Unicode PCRE support
    // On non-UTF8 systems this will result in a blank string.
    $UnicodeSupport = (preg_replace('`[\pP]`u', '', 'P') != '');

    // Convert punctuation, symbols, and spaces to hyphens
    if ($UnicodeSupport) {
        $Str = preg_replace('`[\pP\pS\s]`u', '-', $Str);
    } else {
        $Str = preg_replace('`[\s_[^\w\d]]`', '-', $Str);
    }

    // Lowercase, no trailing or repeat hyphens
    $Str = preg_replace('`-+`', '-', strtolower($Str));
    $Str = trim($Str, '-');

    return rawurlencode($Str);
}

/**
 * Decode the HTML out of a value.
 */
function HTMLDecoder($Value) {
    $CharacterSet = (defined('PORTER_CHARACTER_SET')) ? PORTER_CHARACTER_SET : 'UTF-8';

    switch ($CharacterSet) {
        case 'latin1':
            $CharacterSet = 'ISO-8859-1';
            break;
        case 'latin9':
            $CharacterSet = 'ISO-8859-15';
            break;
        case 'utf8':
            $CharacterSet = 'UTF-8';
            break;
    }

    return html_entity_decode($Value, ENT_QUOTES, $CharacterSet);
}

/**
 * Inverse int value.
 *
 * @param $value
 * @return int
 */
function notFilter($Value) {
    return (int)(!$Value);
}

/**
 * Convert a timestamp to MySQL date format.
 *
 * Do this in MySQL with FROM_UNIXTIME() instead whenever possible.
 *
 * @param $value
 * @return null|string
 */
function timestampToDate($Value) {
    if ($Value == null) {
        return null;
    } else {
        return gmdate('Y-m-d H:i:s', $Value);
    }
}

/**
 * Wrapper for long2ip that nulls 'false' values.
 *
 * @param $value
 * @return null|string
 */
function long2ipf($Value) {
    if (!$Value) {
        return null;
    }

    return long2ip($Value);
}

/**
 * Convert 'y/n' to boolean.
 *
 * @param $value
 * @return int
 */
function YNBool($Value) {
    if ($Value == 'y') {
        return 1;
    } else {
        return 0;
    }
}

/**
 * Guess the Format of the Body.
 *
 * @param $value
 * @return string
 */
function guessFormat($Value) {
    if (strpos($Value, '[') !== false) {
        return 'BBCode';
    } elseif (strpos($Value, '<') !== false) {
        return 'Html';
    } else {
        return 'BBCode';
    }
}

/**
 * Derive mimetype from file extension.
 *
 * @param $value
 * @return string
 */
function mimeTypeFromExtension($value) {

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
}

/**
 * Change square brackets to braces.
 *
 * @param $value
 * @return mixed
 */
function cleanBodyBrackets($Value) {
    if (strpos($Value, '[') !== false) {
        $Result = str_replace(array('<', '>'), array('[', ']'), $Value);

        return $Result;
    }

    return $Value;
}

?>
