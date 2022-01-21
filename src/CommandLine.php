<?php

/**
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL2
 */

namespace NitroPorter;

class CommandLine
{
    public const RUN_OPTIONS = [
        // Used shortcodes: s, t, p, o, h
        'source' => [
            'Alias of a connection in the config.',
            'Req' => true,
            'Sx' => ':',
            'Short' => 's',
        ],
        'target' => [
            'Alias of a connection in the config.',
            'Req' => true,
            'Sx' => ':',
            'Short' => 't',
            'Default' => 'vanilla-csv',
        ],
        'package' => [
            'Source software package alias.',
            'Req' => true,
            'Sx' => ':',
            'Short' => 'p',
        ],
        'output' => [
            'Target software package alias.',
            'Req' => true,
            'Sx' => ':',
            'Short' => 'o',
        ],
        'help' => [
            'Show this help, duh.',
            'Short' => 'h',
        ],
        'src-prefix' => [
            'Source database table prefix.',
            'Sx' => ':',
            'Default' => '',
        ],
        'tar-prefix' => [
            'Target database table prefix.',
            'Sx' => ':',
            'Default' => '',
        ],
        'tables' => [
            'Selective export, limited to specified tables, if provided.',
            'Sx' => ':',
        ],
    ];

    /**
     * @return array
     */
    public function getGlobalOptions(): array
    {
        $options = self::RUN_OPTIONS;
        $options['package']['Values'] = array_keys(SupportManager::getInstance()->getSupport());
        return $options;
    }

    /**
     * @param bool $sections
     * @return array
     */
    public function getAllOptions($sections = false): array
    {
        $globalOptions = $this->getGlobalOptions();
        $supported = SupportManager::getInstance()->getSupport();
        $result = [];

        if ($sections) {
            $result['Global Options'] = $globalOptions;
        } else {
            $result = $globalOptions;
        }

        foreach ($supported as $type => $options) {
            $commandLine = v('options', $options);
            if (!$commandLine) {
                continue;
            }

            if ($sections) {
                $result[$options['name']] = $commandLine;
            } else {
                // We need to add the types to each command line option for validation purposes.
                foreach ($commandLine as $longCode => $row) {
                    if (isset($result[$longCode])) {
                        $result[$longCode]['Packages'][] = $type;
                    } else {
                        $row['Packages'] = array($type);
                        $result[$longCode] = $row;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param array $options
     * @return array
     */
    public function getOptCodes(array $options): array
    {
        $shortCodes = '';
        $longCodes = array();

        foreach ($options as $longCode => $row) {
            $sx = v('Sx', $row, '');
            $short = v('Short', $row, '');

            if ($short) {
                $shortCodes .= $short . $sx;
            }
            $longCodes[] = $longCode . $sx;
        }

        return array($shortCodes, $longCodes);
    }

    /**
     * Main process for bootstrap.
     */
    public function parse(): void
    {
        // Get the inputs.
        $commandOptions = $this->getAllOptions();
        list($shortCodes, $longCodes) = $this->getOptCodes($commandOptions);
        $opts = $this->getOptFromArgv($shortCodes, $longCodes);

        // Output help.
        if (array_key_exists('help', $opts) || array_key_exists('h', $opts)) {
            $options = $this->getAllOptions(true);
            $this->outputHelp($options);
            die();
        }

        // Spawn new package.
        if (isset($opts['spawn']) || isset($opts['s'])) {
            $name = (isset($opts['spawn'])) ? $opts['spawn'] : $opts['s'];
            spawnPackage($name);
            die();
        }

        $_POST = $this->validate($opts, $commandOptions);
    }

    /**
     * Basically does the same thing than getopt() with one minor difference.
     *
     * The difference is that an empty option (-o="" || -o= || --option="" || --option=)
     * will show up in the result as if the the option is set with an empty value.
     *
     * @see getopt
     *
     * @param  $shortCodes
     * @param  $longCodes
     * @return array
     */
    public function getOptFromArgv($shortCodes, $longCodes)
    {
        global $argv; // @see https://www.php.net/manual/en/reserved.variables.argv.php

        $options = array();

        $shortCodesArray = array();
        $longCodesArray = array();

        $matches = array();
        if (
            strlen($shortCodes) > 1 &&
            preg_match_all('#([a-z\d])(:{0,2})#i', $shortCodes, $matches, PREG_SET_ORDER) != false
        ) {
            foreach ($matches as $match) {
                $shortCodesArray[$match[1]] = strlen($match[2]);
            }
        }

        foreach ($longCodes as $longCode) {
            $explodedLongCode = explode(':', $longCode);
            $longCodesArray[$explodedLongCode[0]] = count($explodedLongCode) - 1;
        }

        $argvCount = count($argv);
        for ($i = 1; $i < $argvCount; $i++) {
            $currentArg = $argv[$i];

            $matches = array();
            if (preg_match('#^(-{1,2})([a-z\d]+)(?:=(.*))?$#i', $currentArg, $matches) === 1) {
                $optionType = $matches[1];
                $optionName = $matches[2];

                $optionValue = isset($matches[3]) ? $matches[3] : null;

                if ($optionType === '-') {
                    $argType = 'short';
                } else {
                    $argType = 'long';
                }

                if (!isset(${$argType . 'CodesArray'}[$optionName])) {
                    continue;
                }

                $optionValueRequirement = ${$argType . 'CodesArray'}[$optionName];

                if ($optionValueRequirement === 0) { // 0 = No value permitted
                    if ($optionValue !== null) {
                        continue;
                    }
                } elseif ($optionValueRequirement === 1) { // 1 = Value required
                    if ($optionValue === null) {
                        continue;
                    }
                }

                $options[$optionName] = $optionValue;
            }
        }

        return $options;
    }

    /**
     * @param array $values
     * @param array $options
     * @return array
     */
    public function validate($values, $options)
    {
        $errors = array();
        $result = array();

        $type = v('package', $values, v('t', $values));

        foreach ($options as $longCode => $row) {
            $req = v('Req', $row);
            $short = v('Short', $row);

            $types = v('Types', $row);

            if ($types && !in_array($type, $types)) {
                continue;
            }

            $value = null;
            if (array_key_exists($longCode, $values)) {
                $value = $values[$longCode];
                if ($value === null) {
                    $value = true;
                }
            } elseif ($short && array_key_exists($short, $values)) {
                $value = $values[$short];
                if ($value === null) {
                    $value = true;
                }
            } elseif (isset($row['Default'])) {
                $value = $row['Default'];
            }

            if ($value === null) {
                $default = v('Default', $row, null);
                if ($default === null) {
                    if ($req) {
                        $errors[] = "Missing required parameter: $longCode";
                    }

                    continue;
                } else {
                    $value = $default;
                }
            }

            if ($allowedValues = v('Values', $row)) {
                if (!in_array($value, $allowedValues)) {
                    $errors[] = "Invalid value for parameter: $longCode. Must be one of: " .
                        implode(', ', $allowedValues);
                    continue;
                }
            }

            $field = v('Field', $row, $longCode);
            $result[$field] = $value;
        }

        // Badly handle errors.
        if (count($errors)) {
            echo implode("\n", $errors) . "\n";
            die();
        }

        return $result;
    }

    /**
     * Output help to the CLI.
     *
     * @param array $options Multi-dimensional array of CLI options.
     */
    public function outputHelp(array $options)
    {
        $output = '';

        foreach ($options as $section => $options) {
            $output .= $section . "\n";

            foreach ($options as $longname => $properties) {
                $output .= $this->buildSingleOptionOutput($longname, $properties) . "\n";
            }

            $output .= "\n";
        }

        echo $output;
    }

    /**
     * Build a single line of help output for a single CLI command.
     *
     * @param string $longname
     * @param array $properties
     * @return string
     */
    public function buildSingleOptionOutput(string $longname, array $properties): string
    {
        // Indent.
        $output = "  ";

        // Short code.
        if (isset($properties['Short'])) {
            $output .= '-' . $properties['Short'] . ', ';
        }

        // Long code.
        $output .= "--$longname";

        // Align descriptions by padding.
        $output = str_pad($output, 20, ' ');

        // Whether param is required.
        if (v('Req', $properties)) {
            $output .= 'Required. ';
        }

        // Description.
        $output .= "{$properties[0]}";

        // List valid values for --type.
        if ($values = v('Values', $properties)) {
            //$output .= ' (Choose from: ' . implode(', ', $values) . ')';
        }

        return $output;
    }
}
